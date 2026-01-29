<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    private Client $client;
    private Drive $service;
    private array $tokens = [];
    private string $currentState = '';

    public function __construct()
    {
        $this->client = new Client();
        $this->setupClient();
        $this->service = new Drive($this->client);
    }

    private function setupClient(): void
    {
        $this->client->setApplicationName(config('app.name') . ' Backup');
        $this->client->setScopes([
            Drive::DRIVE_FILE,
            Drive::DRIVE_METADATA_READONLY
        ]);

        // Setup OAuth2 credentials
        $this->client->setClientId(config('services.google_drive.client_id'));
        $this->client->setClientSecret(config('services.google_drive.client_secret'));
        $this->client->setRedirectUri(config('services.google_drive.redirect_uri'));

        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->setApprovalPrompt('force');
    }

    /**
     * Generate authorization URL for OAuth flow - SqlBak style
     */
    public function getAuthorizationUrl(): string
    {
        $this->currentState = bin2hex(random_bytes(16));
        $this->client->setState($this->currentState);

        return $this->client->createAuthUrl();
    }

    /**
     * Get current state for verification
     */
    public function getState(): string
    {
        return $this->currentState;
    }

    /**
     * Exchange authorization code for access tokens
     */
    public function exchangeCodeForTokens(string $code): ?array
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                Log::error('Google OAuth token exchange error: ' . $accessToken['error']);
                return null;
            }

            $this->tokens = $accessToken;
            $this->client->setAccessToken($accessToken);

            return $accessToken;
        } catch (\Exception $e) {
            Log::error('Failed to exchange code for tokens: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set tokens for authenticated requests
     */
    public function setTokens(array $tokens): bool
    {
        try {
            $this->tokens = $tokens;

            // Refresh token if needed
            if ($this->client->isAccessTokenExpired()) {
                if (isset($tokens['refresh_token'])) {
                    $newTokens = $this->client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                    if (!isset($newTokens['error'])) {
                        $this->tokens = array_merge($tokens, $newTokens);
                    }
                }
            }

            $this->client->setAccessToken($this->tokens);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set Google Drive tokens: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get folders from Google Drive - SqlBak style folder picker
     */
    public function getFolders(string $parentId = null): array
    {
        try {
            $query = "mimeType='application/vnd.google-apps.folder'";
            if ($parentId) {
                $query .= " and '{$parentId}' in parents";
            } else {
                $query .= " and 'root' in parents";
            }

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,parents,createdTime)',
                'orderBy' => 'name'
            ]);

            $folders = [];
            foreach ($response->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'parent_id' => $parentId,
                    'created_time' => $file->getCreatedTime()
                ];
            }

            return $folders;
        } catch (\Exception $e) {
            Log::error('Failed to get Google Drive folders: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Authenticate với Google Drive - SqlBak style
     */
    public function authenticate(array $credentials): bool
    {
        try {
            if (isset($credentials['tokens'])) {
                // OAuth2 tokens from SqlBak-style flow
                return $this->setTokens($credentials['tokens']);
            } elseif (isset($credentials['access_token'])) {
                // Direct tokens
                return $this->setTokens($credentials);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Google Drive authentication failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload file lên Google Drive
     */
    public function uploadFile(string $filePath, string $fileName, string $folderId = null): ?string
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => $folderId ? [$folderId] : null
            ]);

            $content = file_get_contents($filePath);
            $mimeType = $this->getMimeType($fileName);

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,size,createdTime'
            ]);

            Log::info("File uploaded to Google Drive successfully", [
                'file_id' => $file->getId(),
                'file_name' => $fileName,
                'size' => filesize($filePath)
            ]);

            return $file->getId();

        } catch (\Exception $e) {
            Log::error('Failed to upload file to Google Drive: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'file_name' => $fileName
            ]);
            return null;
        }
    }

    /**
     * Tạo thư mục trên Google Drive
     */
    public function createFolder(string $folderName, string $parentFolderId = null): ?string
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => $parentFolderId ? [$parentFolderId] : null
            ]);

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->getId();
        } catch (\Exception $e) {
            Log::error('Failed to create folder on Google Drive: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy danh sách file trong thư mục
     */
    public function listFiles(string $folderId = null): array
    {
        try {
            $query = $folderId ? "'{$folderId}' in parents" : "";

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,size,createdTime,mimeType)',
                'orderBy' => 'createdTime desc'
            ]);

            return $response->getFiles();
        } catch (\Exception $e) {
            Log::error('Failed to list files from Google Drive: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Xóa file trên Google Drive
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->service->files->delete($fileId);
            Log::info("File deleted from Google Drive", ['file_id' => $fileId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file from Google Drive: ' . $e->getMessage(), [
                'file_id' => $fileId
            ]);
            return false;
        }
    }

    /**
     * Lấy thông tin file
     */
    public function getFileInfo(string $fileId): ?array
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id,name,size,createdTime,mimeType,webViewLink'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'created_time' => $file->getCreatedTime(),
                'mime_type' => $file->getMimeType(),
                'web_view_link' => $file->getWebViewLink()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get file info from Google Drive: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dọn dẹp file cũ theo retention policy
     */
    public function cleanupOldFiles(string $folderId, int $retentionDays): int
    {
        try {
            $cutoffDate = now()->subDays($retentionDays)->toISOString();
            $query = "'{$folderId}' in parents and createdTime < '{$cutoffDate}'";

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,createdTime)'
            ]);

            $deletedCount = 0;
            foreach ($response->getFiles() as $file) {
                if ($this->deleteFile($file->getId())) {
                    $deletedCount++;
                }
            }

            Log::info("Cleaned up old backup files from Google Drive", [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old files from Google Drive: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy MIME type dựa trên extension
     */
    private function getMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match($extension) {
            'zip' => 'application/zip',
            'sql' => 'application/sql',
            'txt' => 'text/plain',
            'json' => 'application/json',
            default => 'application/octet-stream'
        };
    }

    /**
     * Test kết nối với Google Drive
     */
    public function testConnection(): bool
    {
        try {
            $this->service->files->listFiles(['pageSize' => 1]);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}

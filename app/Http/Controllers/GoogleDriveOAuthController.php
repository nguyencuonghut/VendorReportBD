<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class GoogleDriveOAuthController extends Controller
{
    private GoogleDriveService $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
        try {
            $authUrl = $this->googleDriveService->getAuthorizationUrl();

            // Lưu state để verify sau
            Session::put('google_drive_state', $this->googleDriveService->getState());

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Google Drive auth URL: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo liên kết xác thực Google Drive: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $error = $request->input('error');

            if ($error) {
                Log::error('Google OAuth returned error: ' . $error);
                return view('google-oauth-callback', [
                    'error' => $error,
                    'success' => false
                ]);
            }

            if (!$code) {
                Log::error('Google OAuth no code received');
                return view('google-oauth-callback', [
                    'error' => 'no_code',
                    'success' => false
                ]);
            }

            // Auto-exchange token ngay lập tức
            $expectedState = Session::get('google_drive_state');

            if ($state !== $expectedState) {
                Log::error('State mismatch', [
                    'expected' => $expectedState,
                    'received' => $state
                ]);
                return view('google-oauth-callback', [
                    'error' => 'state_mismatch',
                    'success' => false
                ]);
            }

            // Exchange code for tokens
            $tokens = $this->googleDriveService->exchangeCodeForTokens($code);

            if (!$tokens) {
                Log::error('Failed to exchange code for tokens');
                return view('google-oauth-callback', [
                    'error' => 'token_exchange_failed',
                    'success' => false
                ]);
            }

            // Save tokens and config to session
            Session::put('google_drive_tokens', $tokens);

            $config = [
                'tokens' => $tokens,
                'folder_id' => null,
                'folder_name' => 'Chưa chọn thư mục',
                'connected_at' => now()->toISOString(),
                'connected' => true,
                'status' => 'connected_no_folder'
            ];

            Session::put('google_drive_config', $config);

            Log::info('Google Drive OAuth successful', [
                'has_tokens' => !empty($tokens),
                'config_saved' => true
            ]);

            return view('google-oauth-callback', [
                'code' => $code,
                'state' => $state,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Google Drive callback error: ' . $e->getMessage());

            return view('google-oauth-callback', [
                'error' => 'callback_exception',
                'success' => false
            ]);
        }
    }

    /**
     * Get Google Drive folders for picker
     */
    public function getFolders(Request $request)
    {
        try {
            $tokens = Session::get('google_drive_tokens');

            Log::info('getFolders - checking session', [
                'has_tokens' => !empty($tokens),
                'session_id' => Session::getId(),
                'all_session_keys' => array_keys(Session::all())
            ]);

            if (!$tokens) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa có tokens. Vui lòng xác thực lại với Google Drive.',
                    'debug' => [
                        'session_id' => Session::getId(),
                        'session_keys' => array_keys(Session::all())
                    ]
                ], 401);
            }

            // Set tokens cho service
            $this->googleDriveService->setTokens($tokens);

            // Lấy danh sách folders
            $folders = $this->googleDriveService->getFolders($request->input('parent_id'));

            return response()->json([
                'success' => true,
                'folders' => $folders
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Google Drive folders: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy danh sách thư mục: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create backup folder in Google Drive
     */
    public function createBackupFolder(Request $request)
    {
        $request->validate([
            'parent_folder_id' => 'nullable|string',
            'folder_name' => 'required|string|max:100'
        ]);

        try {
            $tokens = Session::get('google_drive_tokens');

            if (!$tokens) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa có tokens. Vui lòng xác thực lại với Google Drive.'
                ], 401);
            }

            $this->googleDriveService->setTokens($tokens);

            $folderId = $this->googleDriveService->createFolder(
                $request->folder_name,
                $request->parent_folder_id
            );

            if ($folderId) {
                return response()->json([
                    'success' => true,
                    'folder_id' => $folderId,
                    'message' => 'Tạo thư mục thành công!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tạo thư mục trên Google Drive.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create Google Drive folder: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi tạo thư mục: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save selected Google Drive folder for backup config
     */
    public function saveFolderSelection(Request $request)
    {
        $request->validate([
            'folder_id' => 'required|string',
            'folder_name' => 'required|string'
        ]);

        try {
            $tokens = Session::get('google_drive_tokens');

            if (!$tokens) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chưa có tokens. Vui lòng xác thực lại với Google Drive.'
                ], 401);
            }

            // Lưu cấu hình Google Drive vào session để dùng khi tạo backup config
            $googleDriveConfig = [
                'tokens' => $tokens,
                'folder_id' => $request->folder_id,
                'folder_name' => $request->folder_name,
                'connected_at' => now()->toISOString()
            ];

            Session::put('google_drive_config', $googleDriveConfig);

            return response()->json([
                'success' => true,
                'config' => [
                    'folder_id' => $request->folder_id,
                    'folder_name' => $request->folder_name,
                    'connected' => true
                ],
                'message' => 'Đã chọn thư mục Google Drive thành công!'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save Google Drive folder selection: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lưu cấu hình: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exchange OAuth code for tokens (called from frontend after popup callback)
     */
    public function exchangeToken(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string'
        ]);

        try {
            // Verify state
            $expectedState = Session::get('google_drive_state');
            $receivedState = $request->state;

            Log::info('State verification', [
                'expected' => $expectedState,
                'received' => $receivedState,
                'match' => $receivedState === $expectedState
            ]);

            if ($receivedState !== $expectedState) {
                return response()->json([
                    'success' => false,
                    'message' => 'State không hợp lệ. Vui lòng thử lại.',
                    'debug' => [
                        'expected' => $expectedState,
                        'received' => $receivedState
                    ]
                ], 400);
            }

            // Exchange code for token
            $tokens = $this->googleDriveService->exchangeCodeForTokens($request->code);

            if (!$tokens) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể lấy access token từ Google Drive.'
                ], 500);
            }

            // Lưu tokens vào session
            Session::put('google_drive_tokens', $tokens);

            // Tạo temporary config cho status check (sẽ được update khi user chọn folder)
            $tempConfig = [
                'tokens' => $tokens,
                'folder_id' => null,
                'folder_name' => 'Chưa chọn thư mục',
                'connected_at' => now()->toISOString(),
                'status' => 'connected_no_folder'
            ];

            Session::put('google_drive_config', $tempConfig);

            return response()->json([
                'success' => true,
                'message' => 'Kết nối Google Drive thành công!',
                'config' => [
                    'folder_id' => null,
                    'folder_name' => 'Chưa chọn thư mục',
                    'connected' => true,
                    'status' => 'connected_no_folder'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to exchange OAuth code: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình xác thực: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Google Drive connection status
     */
    public function getConnectionStatus(Request $request)
    {
        try {
            $googleDriveConfig = Session::get('google_drive_config');

            if ($googleDriveConfig) {
                return response()->json([
                    'success' => true,
                    'connected' => true,
                    'config' => [
                        'folder_id' => $googleDriveConfig['folder_id'] ?? null,
                        'folder_name' => $googleDriveConfig['folder_name'] ?? 'Unknown',
                        'connected_at' => $googleDriveConfig['connected_at'] ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'config' => null
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to check Google Drive status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Không thể kiểm tra trạng thái Google Drive.'
            ], 500);
        }
    }

    /**
     * Disconnect Google Drive
     */
    public function disconnect(Request $request)
    {
        // Xóa tokens và config khỏi session
        Session::forget(['google_drive_tokens', 'google_drive_config', 'google_drive_state']);

        return response()->json([
            'success' => true,
            'message' => 'Đã ngắt kết nối Google Drive.'
        ]);
    }
}

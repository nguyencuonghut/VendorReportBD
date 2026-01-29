<template>
  <div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Quản Lý Roles</h1>
            <p class="mt-2 text-sm text-gray-600">
              Quản lý các vai trò và phân quyền trong hệ thống
            </p>
          </div>
          <button
            @click="createRole"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Tạo Role Mới
          </button>
        </div>
      </div>

      <!-- Roles Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="role in roles"
          :key="role.id"
          class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200"
        >
          <div class="p-6">
            <!-- Role Header -->
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div
                    class="h-12 w-12 rounded-full flex items-center justify-center"
                    :class="getRoleBadgeColor(role.name)"
                  >
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <h3 class="text-lg font-medium text-gray-900 capitalize">
                    {{ role.name }}
                  </h3>
                  <p class="text-sm text-gray-500">
                    {{ role.users_count || 0 }} users
                  </p>
                </div>
              </div>
            </div>

            <!-- Permissions -->
            <div class="mb-4">
              <h4 class="text-sm font-medium text-gray-700 mb-2">Permissions</h4>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="permission in role.permissions.slice(0, 5)"
                  :key="permission.id"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
                >
                  {{ permission.name }}
                </span>
                <span
                  v-if="role.permissions.length > 5"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                >
                  +{{ role.permissions.length - 5 }} more
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-2">
              <button
                @click="viewRole(role)"
                class="text-sm text-indigo-600 hover:text-indigo-900 font-medium"
              >
                Xem
              </button>
              <button
                @click="editRole(role)"
                class="text-sm text-blue-600 hover:text-blue-900 font-medium"
              >
                Sửa
              </button>
              <button
                v-if="!isSystemRole(role.name)"
                @click="deleteRole(role)"
                class="text-sm text-red-600 hover:text-red-900 font-medium"
              >
                Xóa
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="roles.length === 0"
        class="text-center py-12 bg-white rounded-lg shadow"
      >
        <svg
          class="mx-auto h-12 w-12 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
          />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có role nào</h3>
        <p class="mt-1 text-sm text-gray-500">Bắt đầu bằng cách tạo role mới.</p>
        <div class="mt-6">
          <button
            @click="createRole"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Tạo Role Mới
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
  roles: {
    type: Array,
    required: true,
  },
});

// Methods
const getRoleBadgeColor = (roleName) => {
  const colors = {
    'super-admin': 'bg-purple-600',
    'admin': 'bg-red-600',
    'manager': 'bg-blue-600',
    'user': 'bg-green-600',
  };
  return colors[roleName] || 'bg-gray-600';
};

const isSystemRole = (roleName) => {
  return ['super-admin', 'admin', 'manager', 'user'].includes(roleName);
};

const createRole = () => {
  router.visit(route('roles.create'));
};

const viewRole = (role) => {
  router.visit(route('roles.show', role.id));
};

const editRole = (role) => {
  router.visit(route('roles.edit', role.id));
};

const deleteRole = (role) => {
  if (confirm(`Bạn có chắc chắn muốn xóa role "${role.name}"?`)) {
    router.delete(route('roles.destroy', role.id));
  }
};
</script>

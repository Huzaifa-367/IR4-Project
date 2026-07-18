export type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    is_read_only: boolean;
    users_count: number;
    permissions: string[];
};

export type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
};

export type RoleOption = {
    id: number;
    name: string;
    is_system: boolean;
    is_read_only: boolean;
};

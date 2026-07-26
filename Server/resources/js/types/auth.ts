export type User = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    must_change_password?: boolean;
    two_factor_enabled?: boolean;
    roles?: string[];
    permissions?: string[];
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

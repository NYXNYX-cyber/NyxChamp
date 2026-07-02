export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: 'student' | 'teacher' | 'admin';
    institution?: string | null;
    notification_preferences?: {
        email_enabled: boolean;
        web_enabled: boolean;
        levels: string[];
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

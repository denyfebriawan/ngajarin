export type Role = 'owner' | 'tutor' | 'student';

export type CurrentTenant = {
    name: string;
    slug: string;
    role: Role;
};

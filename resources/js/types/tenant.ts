export type Role = 'owner' | 'tutor' | 'student';

export type CurrentTenant = {
    name: string;
    slug: string;
    role: Role;
    // Which actions the UI should offer. The server still checks every action itself.
    can: {
        update: boolean;
    };
};

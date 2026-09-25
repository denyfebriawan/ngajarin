export type Role = 'owner' | 'tutor' | 'student';

// A workspace the signed-in user belongs to (the sidebar, the dashboard), with their role in it.
export type TenantSummary = {
    name: string;
    slug: string;
    role: Role;
};

// The workspace the current page belongs to.
export type CurrentTenant = TenantSummary & {
    // IANA name, e.g. "Asia/Jakarta". Availability hours are local times in this zone.
    timezone: string;
    // Which actions the UI should offer. The server still checks every action itself.
    can: {
        update: boolean;
        manageSubjects: boolean;
        teach: boolean;
    };
};

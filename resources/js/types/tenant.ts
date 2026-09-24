export type Role = 'owner' | 'tutor' | 'student';

// A workspace in a list (the sidebar, the dashboard).
export type TenantSummary = {
    name: string;
    slug: string;
};

// The workspace the current page belongs to.
export type CurrentTenant = TenantSummary & {
    // IANA name, e.g. "Asia/Jakarta". Availability hours are local times in this zone.
    timezone: string;
    role: Role;
    // Which actions the UI should offer. The server still checks every action itself.
    can: {
        update: boolean;
        manageSubjects: boolean;
    };
};

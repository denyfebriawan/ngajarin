import type { Auth } from '@/types/auth';
import type { CurrentTenant, TenantSummary } from '@/types/tenant';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            // Whether to offer the one-click demo logins.
            demoEnabled: boolean;
            sidebarOpen: boolean;
            currentTenant: CurrentTenant | null;
            tenants: TenantSummary[];
            [key: string]: unknown;
        };
    }
}

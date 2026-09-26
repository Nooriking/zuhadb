import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const BLOCKED = ['edit', 'delete', 'trash', 'toggle', 'deactivate', 'restore', 'remove'];

export function HideManageActions() {
    const page = usePage();
    const access = (page.props as { access?: { is_admin?: boolean; is_super_admin?: boolean } }).access;
    const canManage = Boolean(access?.is_super_admin || access?.is_admin);

    useEffect(() => {
        if (canManage) return;

        const root = document.getElementById('app') ?? document.body;

        root.querySelectorAll('a').forEach((anchor) => {
            const href = (anchor.getAttribute('href') || '').toLowerCase();
            const text = (anchor.textContent || '').toLowerCase();
            if (href.includes('/edit') || href.includes('/trash') || text.includes('edit') || text.includes('trash') || text.includes('delete')) {
                anchor.style.display = 'none';
            }
        });

        root.querySelectorAll('button').forEach((button) => {
            const label = `${button.getAttribute('title') || ''} ${button.textContent || ''}`.toLowerCase();
            if (BLOCKED.some((word) => label.includes(word))) {
                button.style.display = 'none';
            }
        });
    }, [canManage, page.url]);

    return null;
}

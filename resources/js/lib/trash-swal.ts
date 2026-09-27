import type { SweetAlertResult } from 'sweetalert2';
import { fire, getSwal } from '@/lib/swal';

const GOLD = '#D4AF37';
const DANGER = '#dc2626';
const MUTED = '#6b7280';

export async function confirmSoftDelete(itemLabel: string): Promise<SweetAlertResult> {
    return fire({
        icon: 'warning',
        title: 'Move to trash?',
        html: `<p><strong>${escapeHtml(itemLabel)}</strong> will be moved to trash.<br/>You can restore it later.</p>`,
        showCancelButton: true,
        focusCancel: true,
        confirmButtonColor: DANGER,
        cancelButtonColor: MUTED,
        confirmButtonText: 'Yes, trash it',
        cancelButtonText: 'Cancel',
    });
}

export async function confirmRestore(itemLabel: string): Promise<SweetAlertResult> {
    return fire({
        icon: 'question',
        title: 'Restore item?',
        html: `<p>Restore <strong>${escapeHtml(itemLabel)}</strong> from trash?</p>`,
        showCancelButton: true,
        confirmButtonColor: GOLD,
        cancelButtonColor: MUTED,
        confirmButtonText: 'Restore',
        cancelButtonText: 'Cancel',
    });
}

export async function confirmForceDelete(itemLabel: string, extraNote?: string): Promise<SweetAlertResult> {
    const Swal = await getSwal();

    return Swal.fire({
        icon: 'error',
        title: 'Delete forever?',
        html: `<p><strong>${escapeHtml(itemLabel)}</strong> will be <strong>permanently deleted</strong>.${
            extraNote ? `<br/><span class="text-sm">${escapeHtml(extraNote)}</span>` : ''
        }<br/><br/>This cannot be undone.</p>`,
        showCancelButton: true,
        focusCancel: true,
        confirmButtonColor: DANGER,
        cancelButtonColor: MUTED,
        confirmButtonText: 'Delete forever',
        cancelButtonText: 'Cancel',
        input: 'text',
        inputPlaceholder: 'Type DELETE to confirm',
        inputValidator: (value) => {
            if (value !== 'DELETE') {
                return 'Type DELETE (all caps) to confirm';
            }
        },
    });
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

import type { SweetAlertOptions, SweetAlertResult } from 'sweetalert2';

type SwalModule = typeof import('sweetalert2').default;

let cached: SwalModule | null = null;

/** Cached dynamic import — keeps sweetalert2 out of the main bundle. */
export async function getSwal(): Promise<SwalModule> {
    if (!cached) {
        cached = (await import('sweetalert2')).default;
    }

    return cached;
}

/** @deprecated use getSwal() */
export async function swal(): Promise<SwalModule> {
    return getSwal();
}

export async function fire(options: SweetAlertOptions): Promise<SweetAlertResult> {
    const Swal = await getSwal();

    return Swal.fire(options);
}

export async function toastError(title: string, text?: string) {
    return fire({
        icon: 'error',
        title,
        text,
        confirmButtonColor: '#D4AF37',
    });
}

export async function toastSuccess(title: string, text?: string) {
    return fire({
        icon: 'success',
        title,
        text,
        confirmButtonColor: '#D4AF37',
        timer: 1800,
        showConfirmButton: false,
    });
}

export async function confirmAction(title: string, text?: string) {
    return fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonColor: '#D4AF37',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel',
    });
}

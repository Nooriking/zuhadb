import { router } from '@inertiajs/react';
import { toastError, toastSuccess } from '@/lib/swal';
import { confirmForceDelete, confirmRestore, confirmSoftDelete } from '@/lib/trash-swal';

type VisitCallbacks = {
    onDone?: () => void;
};

export async function softDeleteItem(label: string, url: string, cb?: VisitCallbacks): Promise<void> {
    const result = await confirmSoftDelete(label);
    if (!result.isConfirmed) {
        return;
    }

    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            void toastSuccess('Moved to Trash', `${label} was moved to trash.`);
            cb?.onDone?.();
        },
        onError: () => {
            void toastError('Error', 'Could not move this item to trash.');
            cb?.onDone?.();
        },
    });
}

export async function restoreItem(label: string, url: string, cb?: VisitCallbacks): Promise<void> {
    const result = await confirmRestore(label);
    if (!result.isConfirmed) {
        return;
    }

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                void toastSuccess('Restored', `${label} was restored.`);
                cb?.onDone?.();
            },
            onError: () => {
                void toastError('Error', 'Could not restore this item.');
                cb?.onDone?.();
            },
        },
    );
}

export async function forceDeleteItem(
    label: string,
    url: string,
    extraNote?: string,
    cb?: VisitCallbacks,
): Promise<void> {
    const result = await confirmForceDelete(label, extraNote);
    if (!result.isConfirmed) {
        return;
    }

    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            void toastSuccess('Deleted', `${label} was permanently deleted.`);
            cb?.onDone?.();
        },
        onError: () => {
            void toastError('Error', 'Could not permanently delete this item.');
            cb?.onDone?.();
        },
    });
}

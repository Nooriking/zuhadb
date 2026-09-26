import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';

interface Notice {
    id: number;
    section: string;
    title: string;
    body: string | null;
    read_at: string | null;
    created_at: string;
}

interface Props {
    notifications: {
        data: Notice[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    section: string | null;
}

export default function NotificationsIndex({ notifications, section }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Notifications', href: '/notifications' }]}>
            <Head title="Notifications" />
            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Notifications</h1>
                    <button
                        type="button"
                        className="rounded-md border px-3 py-2 text-sm"
                        onClick={() => router.post('/notifications/read-all')}
                    >
                        Mark all read
                    </button>
                </div>
                    {section && <p className="text-sm text-gray-500">Section: {section}</p>}
                <div className="overflow-hidden rounded-lg border bg-white">
                    {notifications.data.length === 0 && <p className="p-6 text-center text-sm text-gray-400">No notifications</p>}
                    {notifications.data.map((item) => (
                        <Link
                            key={item.id}
                            href={`/notifications/${item.id}/open`}
                            className={`block border-b px-4 py-3 last:border-b-0 hover:bg-gray-50 ${item.read_at ? 'opacity-70' : ''}`}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="font-medium">{item.title}</p>
                                <span className="text-xs uppercase text-gray-400">{item.section}</span>
                            </div>
                            {item.body && <p className="mt-1 text-sm text-gray-600">{item.body}</p>}
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

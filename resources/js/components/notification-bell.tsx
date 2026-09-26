import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

interface Notice {
    id: number;
    section: string;
    title: string;
    body: string | null;
    read_at: string | null;
    created_at: string;
}

export function NotificationBell() {
    const { notifications } = usePage().props as {
        notifications?: { unread: number; items: Notice[] };
    };
    const unread = notifications?.unread ?? 0;
    const items = notifications?.items ?? [];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative h-9 w-9" aria-label="Notifications">
                    <Bell className="h-4 w-4" />
                    {unread > 0 && (
                        <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-80 p-0" align="end" sideOffset={8}>
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <p className="text-sm font-semibold">Notifications</p>
                    <Link href="/notifications" className="text-xs text-blue-600">
                        All
                    </Link>
                </div>
                <div className="max-h-80 overflow-y-auto">
                    {items.length === 0 && <p className="text-muted-foreground px-3 py-6 text-center text-sm">No notifications</p>}
                    {items.map((item) => (
                        <Link
                            key={item.id}
                            href={`/notifications/${item.id}/open`}
                            className={`block border-b px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50 ${item.read_at ? 'opacity-70' : ''}`}
                        >
                            <p className="font-medium">{item.title}</p>
                            {item.body && <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">{item.body}</p>}
                            <p className="text-muted-foreground mt-1 text-[10px] uppercase">{item.section}</p>
                        </Link>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

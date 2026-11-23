import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { aiFetch, readAiError } from '@/lib/ai-fetch';
import { cn } from '@/lib/utils';
import { confirmAction } from '@/lib/swal';
import { Head } from '@inertiajs/react';
import { Loader2, Menu, MessageSquarePlus, SendHorizontal, Sparkles, Trash2 } from 'lucide-react';
import { FormEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

function syncConversationUrl(id: number | null) {
    const url = id ? `/ai?conversation=${id}` : '/ai';
    window.history.replaceState({}, '', url);
}

type ConversationSummary = {
    id: number;
    title: string | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
};

type ChatMessage = {
    id: number | string;
    role: string;
    content: string;
    created_at?: string | null;
    pending?: boolean;
};

type PaginatedConversations = {
    data: ConversationSummary[];
    current_page?: number;
    last_page?: number;
};

type Props = {
    aiEnabled: boolean;
    maxMessageChars: number;
    conversations: PaginatedConversations;
    activeConversation: ConversationSummary | null;
    messages: ChatMessage[];
};

function conversationLabel(c: ConversationSummary): string {
    return c.title?.trim() || 'New conversation';
}

export default function AiChatIndex({
    aiEnabled,
    maxMessageChars,
    conversations: initialConversations,
    activeConversation,
    messages: initialMessages,
}: Props) {
    const [conversations, setConversations] = useState<ConversationSummary[]>(initialConversations.data ?? []);
    const [active, setActive] = useState<ConversationSummary | null>(activeConversation);
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages ?? []);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [loadingConversation, setLoadingConversation] = useState(false);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        setConversations(initialConversations.data ?? []);
        setActive(activeConversation);
        setMessages(initialMessages ?? []);
    }, [initialConversations, activeConversation, initialMessages]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, sending]);

    const canSend = aiEnabled && !!active && !sending && draft.trim().length > 0 && draft.trim().length <= maxMessageChars;

    async function createConversation() {
        if (creating) return;
        setCreating(true);
        setError(null);
        try {
            const res = await aiFetch('/ai/conversations', {
                method: 'POST',
                body: JSON.stringify({}),
            });
            if (!res.ok) {
                throw new Error(await readAiError(res));
            }
            const body = await res.json();
            const created = body.conversation as ConversationSummary;
            setConversations((prev) => [created, ...prev.filter((c) => c.id !== created.id)]);
            setActive(created);
            setMessages([]);
            setMobileOpen(false);
            syncConversationUrl(created.id);
        } catch (e) {
            const message = e instanceof Error ? e.message : 'Could not start a conversation.';
            setError(message);
            toast.error(message);
        } finally {
            setCreating(false);
        }
    }

    async function selectConversation(id: number) {
        if (loadingConversation || sending || active?.id === id) {
            setMobileOpen(false);
            return;
        }
        setLoadingConversation(true);
        setError(null);
        try {
            const res = await aiFetch(`/ai/conversations/${id}`);
            if (!res.ok) {
                throw new Error(await readAiError(res));
            }
            const body = await res.json();
            setActive(body.conversation);
            setMessages(body.messages ?? []);
            setMobileOpen(false);
            syncConversationUrl(id);
        } catch (e) {
            const message = e instanceof Error ? e.message : 'Could not load conversation.';
            setError(message);
            toast.error(message);
        } finally {
            setLoadingConversation(false);
        }
    }

    async function deleteConversation(id: number) {
        const result = await confirmAction('Delete conversation?', 'This removes the chat from your history.');
        if (!result.isConfirmed) return;

        try {
            const res = await aiFetch(`/ai/conversations/${id}`, { method: 'DELETE' });
            if (!res.ok) {
                throw new Error(await readAiError(res));
            }
            setConversations((prev) => prev.filter((c) => c.id !== id));
            if (active?.id === id) {
                setActive(null);
                setMessages([]);
                syncConversationUrl(null);
            }
            toast.success('Conversation deleted');
        } catch (e) {
            const message = e instanceof Error ? e.message : 'Could not delete conversation.';
            toast.error(message);
        }
    }

    async function sendMessage() {
        if (!canSend || !active) return;

        const text = draft.trim();
        setDraft('');
        setSending(true);
        setError(null);

        const tempId = `temp-${Date.now()}`;
        setMessages((prev) => [
            ...prev,
            { id: tempId, role: 'user', content: text, pending: true },
        ]);

        try {
            const res = await aiFetch('/ai/chat', {
                method: 'POST',
                body: JSON.stringify({
                    conversation_id: active.id,
                    message: text,
                }),
            });

            if (!res.ok) {
                // Keep the user message visible if the server may have persisted it; refresh from API.
                const message = await readAiError(res);
                setError(message);
                toast.error(message);
                try {
                    const refresh = await aiFetch(`/ai/conversations/${active.id}`);
                    if (refresh.ok) {
                        const body = await refresh.json();
                        setMessages(body.messages ?? []);
                        if (body.conversation) {
                            setActive(body.conversation);
                            setConversations((prev) => {
                                const next = prev.filter((c) => c.id !== body.conversation.id);
                                return [body.conversation, ...next];
                            });
                        }
                    } else {
                        setMessages((prev) => prev.filter((m) => m.id !== tempId));
                        setDraft(text);
                    }
                } catch {
                    setMessages((prev) => prev.filter((m) => m.id !== tempId));
                    setDraft(text);
                }
                return;
            }

            const body = await res.json();
            setMessages((prev) => {
                const withoutTemp = prev.filter((m) => m.id !== tempId);
                return [
                    ...withoutTemp,
                    body.user_message,
                    body.assistant_message,
                ];
            });
            if (body.conversation) {
                setActive(body.conversation);
                setConversations((prev) => {
                    const next = prev.filter((c) => c.id !== body.conversation.id);
                    return [body.conversation, ...next];
                });
            }
        } catch {
            setMessages((prev) => prev.filter((m) => m.id !== tempId));
            setDraft(text);
            const message = 'Network error. Check your connection and try again.';
            setError(message);
            toast.error(message);
        } finally {
            setSending(false);
            textareaRef.current?.focus();
        }
    }

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        void sendMessage();
    }

    function onKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void sendMessage();
        }
    }

    const sidebar = (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-2 border-b p-3">
                <Button
                    type="button"
                    className="w-full"
                    onClick={() => void createConversation()}
                    disabled={creating}
                    aria-label="Start new chat"
                >
                    {creating ? <Loader2 className="animate-spin" /> : <MessageSquarePlus />}
                    New chat
                </Button>
            </div>
            <div className="flex-1 overflow-y-auto p-2" role="list" aria-label="Conversations">
                {conversations.length === 0 && (
                    <p className="text-muted-foreground px-2 py-6 text-center text-sm">No conversations yet.</p>
                )}
                {conversations.map((c) => {
                    const isActive = active?.id === c.id;
                    return (
                        <div
                            key={c.id}
                            role="listitem"
                            className={cn(
                                'group mb-1 flex items-center gap-1 rounded-md border border-transparent px-2 py-2 text-sm',
                                isActive ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/60',
                            )}
                        >
                            <button
                                type="button"
                                className="min-w-0 flex-1 truncate text-start"
                                onClick={() => void selectConversation(c.id)}
                                aria-current={isActive ? 'page' : undefined}
                            >
                                {conversationLabel(c)}
                            </button>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-destructive rounded p-1 opacity-70 group-hover:opacity-100"
                                aria-label={`Delete ${conversationLabel(c)}`}
                                onClick={() => void deleteConversation(c.id)}
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    );
                })}
            </div>
        </div>
    );

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'AI Assistant', href: '/ai' }]}>
            <Head title="AI Assistant" />

            <div className="flex h-[calc(100vh-7rem)] min-h-[28rem] flex-col gap-0 overflow-hidden border-y md:mx-4 md:mt-2 md:mb-4 md:rounded-lg md:border">
                <div className="bg-muted/30 flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div className="flex min-w-0 items-center gap-2">
                        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                            <SheetTrigger asChild>
                                <Button type="button" variant="outline" size="icon" className="md:hidden" aria-label="Open conversations">
                                    <Menu />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-[18rem] p-0">
                                <SheetHeader className="sr-only">
                                    <SheetTitle>Conversations</SheetTitle>
                                </SheetHeader>
                                {sidebar}
                            </SheetContent>
                        </Sheet>
                        <Sparkles className="text-primary size-5 shrink-0" aria-hidden />
                        <div className="min-w-0">
                            <h1 className="truncate text-base font-semibold tracking-tight">
                                {active ? conversationLabel(active) : 'AI Assistant'}
                            </h1>
                            <p className="text-muted-foreground truncate text-xs">
                                General guidance only — no live ERP data access in this phase.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1">
                    <aside className="bg-background hidden w-72 shrink-0 border-e md:block" aria-label="Conversation list">
                        {sidebar}
                    </aside>

                    <section className="flex min-w-0 flex-1 flex-col" aria-label="Chat">
                        {!aiEnabled && (
                            <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900" role="status">
                                AI is disabled. Ask an administrator to enable it and configure the provider.
                            </div>
                        )}

                        {error && (
                            <div className="border-destructive/30 bg-destructive/5 text-destructive border-b px-4 py-2 text-sm" role="alert">
                                {error}
                            </div>
                        )}

                        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-6">
                            {loadingConversation && (
                                <div className="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm">
                                    <Loader2 className="animate-spin" /> Loading conversation…
                                </div>
                            )}

                            {!loadingConversation && !active && (
                                <div className="text-muted-foreground flex flex-col items-center justify-center gap-3 py-20 text-center">
                                    <Sparkles className="size-8 opacity-60" />
                                    <p className="text-base font-medium text-foreground">Start a conversation</p>
                                    <p className="max-w-sm text-sm">
                                        Ask about perfume retail concepts, planning, or general ERP workflows. Live stock and sales data are not available yet.
                                    </p>
                                    <Button type="button" onClick={() => void createConversation()} disabled={creating}>
                                        {creating ? <Loader2 className="animate-spin" /> : <MessageSquarePlus />}
                                        New chat
                                    </Button>
                                </div>
                            )}

                            {!loadingConversation && active && messages.length === 0 && !sending && (
                                <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-16 text-center">
                                    <p className="text-sm">No messages yet. Ask a question to begin.</p>
                                </div>
                            )}

                            {!loadingConversation &&
                                messages.map((m) => {
                                    const isUser = m.role === 'user';
                                    return (
                                        <div
                                            key={m.id}
                                            className={cn('flex w-full', isUser ? 'justify-end' : 'justify-start')}
                                        >
                                            <div
                                                className={cn(
                                                    'max-w-[min(42rem,92%)] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap break-words',
                                                    isUser
                                                        ? 'bg-primary text-primary-foreground rounded-br-md'
                                                        : 'bg-muted text-foreground rounded-bl-md',
                                                    m.pending && 'opacity-70',
                                                )}
                                            >
                                                <span className="sr-only">{isUser ? 'You: ' : 'Assistant: '}</span>
                                                {m.content}
                                            </div>
                                        </div>
                                    );
                                })}

                            {sending && (
                                <div className="flex justify-start" aria-live="polite">
                                    <div className="bg-muted text-muted-foreground flex items-center gap-2 rounded-2xl rounded-bl-md px-4 py-3 text-sm">
                                        <Loader2 className="size-4 animate-spin" />
                                        Thinking…
                                    </div>
                                </div>
                            )}

                            <div ref={bottomRef} />
                        </div>

                        <form onSubmit={onSubmit} className="border-t p-3 md:p-4">
                            <div className="flex items-end gap-2">
                                <Textarea
                                    ref={textareaRef}
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={onKeyDown}
                                    placeholder={active ? 'Write a message… (Enter to send, Shift+Enter for new line)' : 'Start a new chat to send a message'}
                                    disabled={!active || sending || !aiEnabled}
                                    rows={2}
                                    maxLength={maxMessageChars}
                                    aria-label="Message"
                                    className="min-h-[3rem] max-h-40 resize-y"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    className="shrink-0"
                                    disabled={!canSend}
                                    aria-label={sending ? 'Sending' : 'Send message'}
                                >
                                    {sending ? <Loader2 className="animate-spin" /> : <SendHorizontal />}
                                </Button>
                            </div>
                            <p className="text-muted-foreground mt-1 text-[11px]">
                                {draft.trim().length}/{maxMessageChars}
                            </p>
                        </form>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

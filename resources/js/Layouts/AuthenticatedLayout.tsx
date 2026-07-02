import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useEffect } from 'react';

const ROLE_LABELS: Record<string, string> = {
    student: 'Siswa',
    teacher: 'Guru',
    admin: 'Admin',
};

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const { unread_notifications_count } = usePage().props.auth as any;
    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const [unreadCount, setUnreadCount] = useState(unread_notifications_count || 0);

    useEffect(() => {
        setUnreadCount(unread_notifications_count || 0);
    }, [unread_notifications_count]);

    useEffect(() => {
        if (!user) return;

        // Listen for new notifications
        const channel = window.Echo.private(`App.Models.User.${user.id}`);
        
        channel.notification((notification: any) => {
            setUnreadCount((prev: number) => prev + 1);
        });

        return () => {
            window.Echo.leave(`App.Models.User.${user.id}`);
        };
    }, [user.id]);
    const role = user.role ?? 'student';

    return (
        <div className="min-h-screen bg-cream">
            <nav className="border-b-3 border-ink bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 justify-between">
                        <div className="flex items-center gap-6">
                            <Link href="/" className="flex items-center">
                                <ApplicationLogo className="block h-12 w-auto" />
                            </Link>

                            <div className="hidden gap-3 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Beranda
                                </NavLink>
                                <NavLink
                                    href={route('competitions.index')}
                                    active={route().current('competitions.*')}
                                >
                                    Lomba
                                </NavLink>
                                <NavLink
                                    href={route('chat.index')}
                                    active={route().current('chat.*')}
                                >
                                    Chat
                                </NavLink>
                                {role === 'admin' && (
                                    <NavLink
                                        href={route('admin.dashboard')}
                                        active={route().current('admin.dashboard')}
                                    >
                                        Admin
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {/* Notification Link */}
                            <div className="relative ms-3">
                                <Link
                                    href={route('notifications.index')}
                                    className="relative inline-flex items-center p-2 border-3 border-black text-black bg-pink-brutal hover:bg-pink-brutal-hover font-bold shadow-brutal-sm transition ease-in-out duration-150"
                                    title="Notifikasi"
                                >
                                    <svg
                                        className="h-5 w-5"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                        />
                                    </svg>
                                    {unreadCount > 0 && (
                                        <span className="absolute -top-2 -right-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-mono font-bold leading-none text-black bg-yellow-brutal border-2 border-black">
                                            {unreadCount}
                                        </span>
                                    )}
                                </Link>
                            </div>

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2 border-3 border-ink bg-cream px-3 py-1.5 font-header font-bold uppercase text-sm tracking-wide shadow-brutal-sm transition-all hover:-translate-x-[2px] hover:-translate-y-[2px] hover:shadow-brutal"
                                            >
                                                <span>{user.name}</span>
                                                <span className="border-2 border-ink bg-brutal-yellow px-1.5 py-0.5 text-xs">
                                                    {ROLE_LABELS[role]}
                                                </span>
                                                <svg
                                                    className="h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profil
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Keluar
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center border-3 border-ink bg-white p-2 text-ink shadow-brutal-sm transition-all hover:shadow-brutal"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden border-t-3 border-ink'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Beranda
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('competitions.index')}
                            active={route().current('competitions.*')}
                        >
                            Lomba
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('chat.index')}
                            active={route().current('chat.*')}
                        >
                            Chat
                        </ResponsiveNavLink>
                        {role === 'admin' && (
                            <ResponsiveNavLink
                                href={route('admin.dashboard')}
                                active={route().current('admin.dashboard')}
                            >
                                Admin
                            </ResponsiveNavLink>
                        )}
                    </div>

                    <div className="border-t-3 border-ink pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-bold text-ink">
                                {user.name}
                            </div>
                            <div className="text-sm font-mono text-ink/70">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                        <ResponsiveNavLink href={route('notifications.index')} active={route().current('notifications.index')}>
                            Notifikasi {unreadCount > 0 ? `(${unreadCount})` : ''}
                        </ResponsiveNavLink>
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Keluar
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b-3 border-ink bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}

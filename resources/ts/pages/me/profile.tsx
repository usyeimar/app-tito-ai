import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { MeLayout } from './me-layout';
import type { User } from '@/types';

interface Props {
    user: User & { avatar_url?: string | null };
    mustVerifyEmail: boolean;
    status?: string;
}

export default function Profile({ user, mustVerifyEmail, status }: Props) {
    const initials = user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    const [name, setName] = useState(user.name);
    const [email, setEmail] = useState(user.email);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const [uploadingAvatar, setUploadingAvatar] = useState(false);
    const [removingAvatar, setRemovingAvatar] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.patch(
            '/me/profile',
            { name, email },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRecentlySuccessful(true);
                    setTimeout(() => setRecentlySuccessful(false), 2000);
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleAvatarUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        setUploadingAvatar(true);
        setErrors({});

        router.post(
            '/me/profile-picture',
            { profile_picture: file },
            {
                preserveScroll: true,
                forceFormData: true,
                onError: (errs) => setErrors(errs),
                onFinish: () => {
                    setUploadingAvatar(false);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    };

    const handleAvatarRemove = () => {
        setRemovingAvatar(true);
        setErrors({});

        router.delete('/me/profile-picture', {
            preserveScroll: true,
            onError: (errs) => setErrors(errs),
            onFinish: () => setRemovingAvatar(false),
        });
    };

    return (
        <>
            <Head title="Profile" />
            <MeLayout user={user} activeTab="profile">
                <div className="rounded-xl border border-border bg-card">
                    <form onSubmit={handleSubmit}>
                        <div className="p-6">
                            <h1 className="text-base font-semibold">Profile</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Update your identity details and avatar.
                            </p>

                            {/* Avatar section */}
                            <div className="mt-6 flex items-center gap-4">
                                <Avatar className="size-14" key={user.avatar_url}>
                                    <AvatarImage
                                        src={user.avatar_url ?? undefined}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="bg-muted text-base">
                                        {initials}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex items-center gap-3">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleAvatarUpload}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={uploadingAvatar}
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                    >
                                        {uploadingAvatar && <Spinner />}
                                        Upload image
                                    </Button>
                                    {user.avatar_url && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-foreground"
                                            disabled={removingAvatar}
                                            onClick={handleAvatarRemove}
                                        >
                                            {removingAvatar && <Spinner />}
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <InputError
                                message={errors.profile_picture}
                                className="mt-2"
                            />

                            {/* Form */}
                            <div className="mt-8 space-y-5">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        placeholder="Your name"
                                        required
                                        autoComplete="name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={email}
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                        placeholder="your@email.com"
                                        required
                                        autoComplete="username"
                                    />
                                    <InputError message={errors.email} />

                                    {mustVerifyEmail &&
                                        user.email_verified_at === null && (
                                            <p className="text-xs text-muted-foreground">
                                                Your email address is
                                                unverified. A verification
                                                email has been sent.
                                            </p>
                                        )}
                                </div>
                            </div>

                            {status === 'verification-link-sent' && (
                                <p className="mt-4 text-sm font-medium text-green-600">
                                    A new verification link has been sent to
                                    your email address.
                                </p>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-end gap-3 border-t border-border bg-muted/30 px-6 py-4">
                            {recentlySuccessful && (
                                <p className="text-sm text-muted-foreground">
                                    Saved
                                </p>
                            )}
                            <Button type="submit" size="sm" disabled={processing}>
                                {processing && <Spinner />}
                                Save profile
                            </Button>
                        </div>
                    </form>
                </div>
            </MeLayout>
        </>
    );
}

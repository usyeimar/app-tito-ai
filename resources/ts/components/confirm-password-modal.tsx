import { useCallback, useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    onClose: () => void;
    onConfirmed: () => void;
    title?: string;
    description?: string;
};

export default function ConfirmPasswordModal({
    open,
    onClose,
    onConfirmed,
    title = 'Confirm your password',
    description = 'For your security, please confirm your password before continuing.',
}: Props) {
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);
    const passwordRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            setTimeout(() => passwordRef.current?.focus(), 100);
        }
    }, [open]);

    const reset = useCallback(() => {
        setPassword('');
        setError('');
        setProcessing(false);
    }, []);

    const handleClose = useCallback(() => {
        reset();
        onClose();
    }, [reset, onClose]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setError('');

        try {
            const response = await fetch('/me/confirm-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify({ password }),
            });

            if (response.ok || response.status === 201) {
                reset();
                onConfirmed();
            } else {
                const data = await response.json().catch(() => null);
                setError(
                    data?.errors?.password?.[0] ||
                        data?.message ||
                        'The provided password is incorrect.',
                );
            }
        } catch {
            setError('An error occurred. Please try again.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>

                    <div className="mt-4 space-y-2">
                        <Label htmlFor="confirm-password">Password</Label>
                        <PasswordInput
                            id="confirm-password"
                            ref={passwordRef}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Enter your password"
                            autoComplete="current-password"
                        />
                        <InputError message={error} />
                    </div>

                    <DialogFooter className="mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !password}>
                            {processing && <Spinner />}
                            Confirm
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

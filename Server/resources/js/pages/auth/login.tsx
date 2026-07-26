import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    timeout?: boolean;
    locked?: boolean;
};

export default function Login({ status, timeout, locked }: Props) {
    const pageUrl = usePage().url;
    const showTimeout =
        timeout ||
        pageUrl.includes('timeout=1') ||
        pageUrl.includes('timeout=true');
    const showLocked =
        locked ||
        pageUrl.includes('locked=1') ||
        pageUrl.includes('locked=true');

    return (
        <>
            <Head title="Log in" />

            {showTimeout && (
                <div className="mb-4 rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-foreground">
                    You were signed out due to inactivity.
                </div>
            )}

            {showLocked && (
                <div className="mb-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-foreground">
                    Account temporarily locked after too many failed attempts.
                    Try again later.
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            Accounts are provisioned by an administrator.
                            Contact your Safety Manager if you need access.
                        </p>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to IR4',
    description:
        'Enter your email and password to access the safety command centre',
};

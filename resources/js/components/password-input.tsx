import { Eye, EyeOff } from 'lucide-react';
import { type ComponentProps, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { __ } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** A password field with the eye that shows what was typed. */
export function PasswordInput({ className, ...props }: Omit<ComponentProps<typeof Input>, 'type'>) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input type={visible ? 'text' : 'password'} className={cn('pe-10', className)} {...props} />

            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="absolute inset-y-0 end-0 h-full text-muted-foreground"
                onClick={() => setVisible((current) => !current)}
                aria-label={__('Toggle password visibility')}
                aria-pressed={visible}
            >
                {visible ? <EyeOff /> : <Eye />}
            </Button>
        </div>
    );
}

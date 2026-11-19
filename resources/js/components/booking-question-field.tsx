import { FormField, InputError } from '@/components/form-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { __ } from '@/lib/i18n';

export interface Question {
    id: number;
    label: string;
    type: 'short_text' | 'long_text' | 'single_choice' | 'multiple_choice';
    required: boolean;
    choices: string[];
}

export function BookingQuestionField({ question, value, error, onChange }: { question: Question; value: string | string[]; error?: string; onChange: (value: string | string[]) => void }) {
    const id = `question-${question.id}`;

    switch (question.type) {
        case 'long_text':
            return (
                <FormField id={id} label={question.label} error={error}>
                    <Textarea id={id} rows={3} required={question.required} value={value as string} onChange={(event) => onChange(event.target.value)} />
                </FormField>
            );
        case 'single_choice':
            return (
                <FormField id={id} label={question.label} error={error}>
                    <Select value={(value as string) || undefined} onValueChange={onChange} required={question.required}>
                        <SelectTrigger id={id} className="w-full">
                            <SelectValue placeholder={__('Choose…')} />
                        </SelectTrigger>
                        <SelectContent>
                            {question.choices.map((choice) => (
                                <SelectItem key={choice} value={choice}>
                                    {choice}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            );
        case 'multiple_choice': {
            const chosen = value as string[];

            return (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">{question.label}</legend>
                    {question.choices.map((choice) => (
                        <div key={choice} className="flex items-center gap-2">
                            <Checkbox
                                id={`${id}-${choice}`}
                                checked={chosen.includes(choice)}
                                onCheckedChange={(checked) => onChange(checked === true ? [...chosen, choice] : chosen.filter((item) => item !== choice))}
                            />
                            <Label htmlFor={`${id}-${choice}`} className="font-normal">
                                {choice}
                            </Label>
                        </div>
                    ))}
                    <InputError message={error} />
                </fieldset>
            );
        }
        default:
            return (
                <FormField id={id} label={question.label} error={error}>
                    <Input id={id} required={question.required} value={value as string} onChange={(event) => onChange(event.target.value)} />
                </FormField>
            );
    }
}

import { useAppearance } from '@/hooks/use-appearance';
import { type Palette, usePalette } from '@/hooks/use-palette';
import { __ } from '@/lib/i18n';

/** Where the reveal circle starts: the middle of the control the person used. */
function originOf(element: HTMLElement): { x: number; y: number } {
    const box = (element.closest('label') ?? element).getBoundingClientRect();

    return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
}

/** A month of tiles with a few bookings, drawn in the colours of the palette (`data-preview` on the label). */
function Preview() {
    const tiles: { off?: boolean; today?: boolean; dots?: ('a' | 'b')[] }[] = [
        { off: true }, { dots: ['a'] }, { dots: ['a', 'b'] }, { dots: ['b'] }, {}, { off: true }, { off: true },
        { dots: ['b'] }, {}, { today: true, dots: ['a'] }, { dots: ['a', 'b', 'a'] }, {}, { off: true }, { off: true },
        {}, { dots: ['a'] }, {}, { dots: ['b'] }, { dots: ['a'] }, { off: true }, { off: true },
    ];

    return (
        <span className="pv" aria-hidden="true">
            <span className="pv__band">
                <i></i>
                <i></i>
                <i></i>
            </span>{' '}
            <span className="pv__grid">
                {tiles.map((tile, index) => (
                    <i key={index} className={['t', tile.off ? 'off' : '', tile.today ? 'today' : ''].filter(Boolean).join(' ')}>
                        {tile.dots?.map((dot, i) => <b key={i} className={dot}></b>)}
                    </i>
                ))}
            </span>{' '}
            <span className="pv__row">
                <span className="pv__btn">{__('Book')}</span>
                <span className="pv__dots">
                    <i className="d0"></i>
                    <i className="d1"></i>
                    <i className="d2"></i>
                </span>
            </span>
        </span>
    );
}

export function StylePicker() {
    const { palette, update } = usePalette();
    const { appearance, updateAppearance } = useAppearance();

    const choices: { value: Palette; name: string; description: string }[] = [
        { value: 'basic', name: __('Mono'), description: __('Clean and neutral, it lets your logo and your colour do the talking.') },
        { value: 'oltremare', name: __('Marine'), description: __('Fresh and reassuring, it inspires trust at first sight.') },
        { value: 'lampone', name: __('Berry'), description: __('Cheerful and refined, for a portal that puts people in a good mood.') },
    ];

    const paletteName = choices.find((choice) => choice.value === palette)?.name ?? '';
    const themeName = appearance === 'dark' ? __('Dark') : appearance === 'light' ? __('Light') : __('System');

    return (
        <section className="section section--tight stile" id="stile" aria-labelledby="h-stile" data-reveal>
            <div className="wrap stile__grid">
                <div className="stile__text">
                    <h2 className="h2" id="h-stile">
                        <span className="rise">
                            <span>
                                {__("Make Atrio look like yours.")}
                            </span>
                        </span>
                    </h2>
                    <p className="lead fade" style={{ "--i": "1" }}>
                        {__("Pick the palette of your portal and back office, add your logo and your business's colour: every detail speaks for you. Your customers see it light or dark, as they prefer. Try them here.")}
                    </p>
                </div>
                <div className="stile__ctrl fade" style={{ "--i": "2" }}>
                    <fieldset className="stile__set">
                        <legend className="stile__legend">
                            {__("Palette")}
                        </legend>
                        <div className="stile__cards">
                            {choices.map((choice) => (
                                <label key={choice.value} className="pal" data-preview={choice.value}>
                                    <input className="pal__in" type="radio" name="stile-palette" value={choice.value} checked={palette === choice.value} onChange={(event) => update(choice.value, originOf(event.currentTarget))} />
                                    <span className="pal__card">
                                        <Preview />{' '}
                                        <span className="pal__name">
                                            {choice.name}
                                            <svg className="i pal__tick" aria-hidden="true">
                                                <use href="#i-check" />
                                            </svg>
                                        </span>{' '}
                                        <span className="pal__desc">{choice.description}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <fieldset className="stile__set">
                        <legend className="stile__legend">
                            {__("Theme")}
                        </legend>
                        <div className="seg">
                            <label className="seg__o">
                                <input className="seg__in" type="radio" name="stile-tema" value="light" checked={appearance === 'light'} onChange={(event) => updateAppearance('light', originOf(event.currentTarget))} />
                                <span className="seg__l">
                                    <svg className="i" aria-hidden="true" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="4" />
                                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                                    </svg>
                                    {__("Light")}
                                </span>
                            </label>
                            {' '}
                            <label className="seg__o">
                                <input className="seg__in" type="radio" name="stile-tema" value="dark" checked={appearance === 'dark'} onChange={(event) => updateAppearance('dark', originOf(event.currentTarget))} />
                                <span className="seg__l">
                                    <svg className="i" aria-hidden="true" viewBox="0 0 24 24">
                                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                                    </svg>
                                    {__("Dark")}
                                </span>
                            </label>
                            {' '}
                            <label className="seg__o">
                                <input className="seg__in" type="radio" name="stile-tema" value="system" checked={appearance === 'system'} onChange={(event) => updateAppearance('system', originOf(event.currentTarget))} />
                                <span className="seg__l">
                                    <svg className="i" aria-hidden="true" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width={18} height={12} rx="2" />
                                        <path d="M8 20h8M12 16v4" />
                                    </svg>
                                    {__("System")}
                                </span>
                            </label>
                        </div>
                    </fieldset>
                    <p className="sr" role="status" id="stile-live">
                        {__('Style: :palette, :theme theme.', { palette: paletteName, theme: themeName })}
                    </p>
                </div>
            </div>
        </section>
    );
}

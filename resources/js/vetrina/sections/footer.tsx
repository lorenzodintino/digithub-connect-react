import { __ } from '@/lib/i18n';
import { BrandMark } from './brand-mark';

export function Footer({ name }: { name: string }) {
    return (
        <footer className="foot">
            <div className="wrap">
                <a className="brand" href="#top">
                    <BrandMark />
                    {name}
                </a>
                <nav aria-label={__("Footer")}>
                    <a className="link" href="#stile">
                        {__("Style")}
                    </a>
                    {' '}
                    <a className="link" href="#prova">
                        {__("Try it")}
                    </a>
                    {' '}
                    <a className="link" href="#funzioni">
                        {__("Features")}
                    </a>
                    {' '}
                    <a className="link" href="#email">
                        {__("Email")}
                    </a>
                    {' '}
                    <a className="link" href="#portale">
                        {__("Portal")}
                    </a>
                    {' '}
                    <a className="link" href="#piani">
                        {__("Plans")}
                    </a>
                    {' '}
                    <a className="link" href="#domande">
                        {__("FAQ")}
                    </a>
                    {' '}
                    <a className="link" href="#demo">
                        {__("Request a demo")}
                    </a>
                </nav>
                <p>
                    {__(":app is developed by", { app: name })}
                    {' '}
                    <a className="link" href="https://www.digithub.it" target="_blank" rel="noopener">
                        {"DigitHub"}
                    </a>
                    {' '}
                    {`· © ${new Date().getFullYear()}`}
                </p>
            </div>
        </footer>
    );
}

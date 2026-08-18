import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import TextField from '../components/fields/TextField';
import RepeatableInput from '../components/fields/RepeatableInput';

const config = window.WPP || {};

export default function CdnTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'cdn' );
    const on = !! draft.enabled;

    const side = (
        <Section title={ __( 'Exclude file(s) from CDN', 'wpp' ) }>
            <RepeatableInput
                value={ draft.exclude }
                onChange={ ( v ) => set( 'exclude', v ) }
                placeholder={ config.siteUrl || '' }
                addLabel={ __( 'Add file', 'wpp' ) }
            />
            <p className="wpp-field__help">
                { __( 'Use {numbers}, {letters} or {any} to match dynamic parts of a URL.', 'wpp' ) }
            </p>
        </Section>
    );

    return (
        <TwoColumn side={ side }>
            <Section>
                <Field label={ __( 'Enable CDN', 'wpp' ) }>
                    <Toggle
                        checked={ on }
                        onChange={ ( v ) => set( 'enabled', v ) }
                        help={ __(
                            'Rewrites static asset URLs (CSS, JavaScript, images) to your CDN hostname.',
                            'wpp'
                        ) }
                    />
                </Field>

                { on && (
                    <Field label={ __( 'CDN hostname', 'wpp' ) }>
                        <TextField
                            value={ draft.hostname }
                            onChange={ ( v ) => set( 'hostname', v ) }
                            placeholder="https://cdn.example.com"
                        />
                    </Field>
                ) }
            </Section>

            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

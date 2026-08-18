import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import RepeatableInput from '../components/fields/RepeatableInput';

const config = window.WPP || {};
const WILDCARD_HELP = __(
    'Use {numbers}, {letters}, {any} or {all} to match dynamic parts of a URL.',
    'wpp'
);

export default function HtmlTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'html' );
    const on = !! draft.enabled;

    const side = (
        <Section title={ __( 'Exclude URL(s) from HTML optimization', 'wpp' ) }>
            <RepeatableInput
                value={ draft.exclude_urls }
                onChange={ ( v ) => set( 'exclude_urls', v ) }
                placeholder={ config.siteUrl || '' }
                addLabel={ __( 'Add URL', 'wpp' ) }
            />
            <p className="wpp-field__help">{ WILDCARD_HELP }</p>
        </Section>
    );

    return (
        <TwoColumn side={ side }>
            <Section>
                <Field label={ __( 'HTML optimization', 'wpp' ) }>
                    <Toggle
                        checked={ on }
                        onChange={ ( v ) => set( 'enabled', v ) }
                        label={ __( 'Enable HTML optimization', 'wpp' ) }
                    />
                </Field>

                { on && (
                    <>
                        <Field label={ __( 'Minify', 'wpp' ) }>
                            <Toggle
                                checked={ draft.minify_normal }
                                onChange={ ( v ) => set( 'minify_normal', v ) }
                                label={ __( 'Normal', 'wpp' ) }
                            />
                            <Toggle
                                checked={ draft.minify_aggressive }
                                onChange={ ( v ) => set( 'minify_aggressive', v ) }
                                label={ __( 'Aggressive', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Remove comments', 'wpp' ) }>
                            <Toggle
                                checked={ draft.remove_comments }
                                onChange={ ( v ) => set( 'remove_comments', v ) }
                                label={ __( 'Remove HTML comments (conditional comments preserved)', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Remove type attribute', 'wpp' ) }>
                            <Toggle
                                checked={ draft.remove_link_type }
                                onChange={ ( v ) => set( 'remove_link_type', v ) }
                                label={ __( 'Remove type="text/css" from link tags', 'wpp' ) }
                            />
                            <Toggle
                                checked={ draft.remove_script_type }
                                onChange={ ( v ) => set( 'remove_script_type', v ) }
                                label={ __( 'Remove type="text/javascript" from script tags', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Remove quotes', 'wpp' ) }>
                            <Toggle
                                checked={ draft.remove_quotes }
                                onChange={ ( v ) => set( 'remove_quotes', v ) }
                                label={ __( 'Remove quotes from attributes where possible', 'wpp' ) }
                            />
                        </Field>
                    </>
                ) }
            </Section>

            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

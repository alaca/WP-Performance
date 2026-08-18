import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import TextField from '../components/fields/TextField';
import SelectField from '../components/fields/SelectField';
import RepeatableInput from '../components/fields/RepeatableInput';
import FileListTable from '../components/FileListTable';
import { action } from '../api';
import { toast } from '../lib/toast';

const config = window.WPP || {};

const FONT_DISPLAY = [
    { value: 'none', label: __( 'Default', 'wpp' ) },
    { value: 'swap', label: 'swap' },
    { value: 'block', label: 'block' },
    { value: 'fallback', label: 'fallback' },
    { value: 'optional', label: 'optional' },
    { value: 'auto', label: 'auto' },
];

export default function CssTab() {
    const { draft, set, setIn, isDirty, saving, save } = useSettingsGroup( 'css' );
    const [ files, setFiles ] = useState( { css: {}, js: {} } );
    const [ rescanning, setRescanning ] = useState( false );

    useEffect( () => {
        action( 'files' ).then( setFiles ).catch( () => {} );
    }, [] );

    const rescan = async () => {
        setRescanning( true );
        try {
            const f = await action( 'files/rescan', { method: 'POST' } );
            setFiles( f );
            toast( f.warning || __( 'File list updated.', 'wpp' ), f.warning ? 'error' : 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not update the file list.', 'wpp' ), 'error' );
        } finally {
            setRescanning( false );
        }
    };

    const side = (
        <>
            <Section title={ __( 'CSS settings', 'wpp' ) }>
                <Field label={ __( 'Minify inline CSS', 'wpp' ) }>
                    <Toggle checked={ draft.minify_inline } onChange={ ( v ) => set( 'minify_inline', v ) } />
                </Field>
                <Field label={ __( 'Remove unused CSS', 'wpp' ) }>
                    <Toggle
                        checked={ draft.remove_unused }
                        onChange={ ( v ) => set( 'remove_unused', v ) }
                        label={ __( 'Inline only used CSS; load the rest async', 'wpp' ) }
                        help={ __(
                            'Heuristic: keeps rules whose classes/ids appear on the page. The full CSS still loads async as a fallback.',
                            'wpp'
                        ) }
                    />
                </Field>
                { draft.remove_unused && (
                    <Field
                        label={ __( 'Used-CSS safelist', 'wpp' ) }
                        help={ __( 'Selectors to always keep, e.g. classes toggled by JavaScript.', 'wpp' ) }
                    >
                        <RepeatableInput
                            value={ draft.used_safelist }
                            onChange={ ( v ) => set( 'used_safelist', v ) }
                            addLabel={ __( 'Add selector', 'wpp' ) }
                        />
                    </Field>
                ) }
                <Field label={ __( 'Asynchronous CSS', 'wpp' ) }>
                    <Toggle
                        checked={ draft.defer }
                        onChange={ ( v ) => set( 'defer', v ) }
                        label={ __( 'Load CSS without render-blocking', 'wpp' ) }
                    />
                </Field>
                { draft.defer && (
                    <>
                        <Field label={ __( 'Exclude from async', 'wpp' ) }>
                            <RepeatableInput
                                value={ draft.file_exclude }
                                onChange={ ( v ) => set( 'file_exclude', v ) }
                                addLabel={ __( 'Add file', 'wpp' ) }
                            />
                        </Field>
                        <Field
                            label={ __( 'Critical CSS', 'wpp' ) }
                            help={ __( 'Above-the-fold CSS, inlined and applied before the async stylesheet loads.', 'wpp' ) }
                        >
                            <TextField
                                multiline
                                rows={ 6 }
                                value={ draft.critical_path }
                                onChange={ ( v ) => set( 'critical_path', v ) }
                            />
                        </Field>
                    </>
                ) }
                <Field label={ __( 'Google Fonts', 'wpp' ) }>
                    <Toggle
                        checked={ draft.combine_fonts }
                        onChange={ ( v ) => set( 'combine_fonts', v ) }
                        label={ __( 'Combine Google Fonts into one request', 'wpp' ) }
                    />
                    <Toggle
                        checked={ draft.host_fonts }
                        onChange={ ( v ) => set( 'host_fonts', v ) }
                        label={ __( 'Host Google Fonts locally and preload them', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Font display', 'wpp' ) }>
                    <SelectField
                        value={ draft.font_display }
                        options={ FONT_DISPLAY }
                        onChange={ ( v ) => set( 'font_display', v ) }
                    />
                </Field>
                <Field label={ __( 'Logged-in users', 'wpp' ) }>
                    <Toggle
                        checked={ draft.disable_loggedin }
                        onChange={ ( v ) => set( 'disable_loggedin', v ) }
                        label={ __( 'Disable CSS optimization for logged-in users', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Resource hints', 'wpp' ) }>
                <Field
                    label={ __( 'DNS prefetch', 'wpp' ) }
                    help={ __( 'Origins to resolve early, e.g. https://fonts.gstatic.com', 'wpp' ) }
                >
                    <RepeatableInput
                        value={ draft.dns_prefetch }
                        onChange={ ( v ) => set( 'dns_prefetch', v ) }
                        addLabel={ __( 'Add origin', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Preconnect', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.preconnect }
                        onChange={ ( v ) => set( 'preconnect', v ) }
                        addLabel={ __( 'Add origin', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Exclude URL(s) from CSS optimization', 'wpp' ) }>
                <RepeatableInput
                    value={ draft.exclude_urls }
                    onChange={ ( v ) => set( 'exclude_urls', v ) }
                    placeholder={ config.siteUrl || '' }
                    addLabel={ __( 'Add URL', 'wpp' ) }
                />
            </Section>
        </>
    );

    return (
        <TwoColumn side={ side }>
            <Section>
                <FileListTable files={ files.css || {} } draft={ draft } setIn={ setIn } />
                <div className="wpp-fl__actions">
                    <button
                        type="button"
                        className="wpp-btn wpp-btn--ghost"
                        disabled={ rescanning }
                        onClick={ rescan }
                    >
                        { rescanning ? __( 'Updating…', 'wpp' ) : __( 'Update files list', 'wpp' ) }
                    </button>
                </div>
            </Section>
            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

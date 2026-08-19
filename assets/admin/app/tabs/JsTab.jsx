import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import RepeatableInput from '../components/fields/RepeatableInput';
import FileListTable from '../components/FileListTable';
import { action } from '../api';
import { toast } from '../lib/toast';

const config = window.WPP || {};

export default function JsTab() {
    const { draft, set, setIn, isDirty, saving, save } = useSettingsGroup( 'js' );
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
            <Section title={ __( 'JavaScript settings', 'wpp' ) }>
                <Field label={ __( 'Minify inline JavaScript', 'wpp' ) }>
                    <Toggle checked={ draft.minify_inline } onChange={ ( v ) => set( 'minify_inline', v ) } />
                </Field>
                <Field label={ __( 'Asynchronous JavaScript', 'wpp' ) }>
                    <Toggle
                        checked={ draft.defer }
                        onChange={ ( v ) => set( 'defer', v ) }
                        label={ __( 'Defer JavaScript to remove render-blocking', 'wpp' ) }
                    />
                </Field>
                { draft.defer && (
                    <Field label={ __( 'Exclude from defer', 'wpp' ) }>
                        <RepeatableInput
                            value={ draft.file_exclude }
                            onChange={ ( v ) => set( 'file_exclude', v ) }
                            addLabel={ __( 'Add file', 'wpp' ) }
                        />
                    </Field>
                ) }

                <Field label={ __( 'Delay until interaction', 'wpp' ) }>
                    <Toggle
                        checked={ draft.delay }
                        onChange={ ( v ) => set( 'delay', v ) }
                        label={ __( 'Delay JavaScript until user interaction', 'wpp' ) }
                        help={ __(
                            'Biggest TBT/INP win: scripts run on first scroll, click, or key press. Exclude anything that must run immediately.',
                            'wpp'
                        ) }
                    />
                </Field>
                { draft.delay && (
                    <Field label={ __( 'Exclude from delay', 'wpp' ) }>
                        <RepeatableInput
                            value={ draft.delay_exclude }
                            onChange={ ( v ) => set( 'delay_exclude', v ) }
                            addLabel={ __( 'Add', 'wpp' ) }
                        />
                    </Field>
                ) }

                <Field label={ __( 'Logged-in users', 'wpp' ) }>
                    <Toggle
                        checked={ draft.disable_loggedin }
                        onChange={ ( v ) => set( 'disable_loggedin', v ) }
                        label={ __( 'Disable JavaScript optimization for logged-in users', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Exclude URL(s) from JavaScript optimization', 'wpp' ) }>
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
                <FileListTable files={ files.js || {} } draft={ draft } setIn={ setIn } />
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

import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { confirm } from '../lib/confirm';
import { RotateCcw } from 'lucide-react';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import { action } from '../api';
import { toast } from '../lib/toast';
import { timeAgo } from '../lib/format';

const GROUP_LABELS = {
    cache: __( 'Cache', 'wpp' ),
    css: __( 'CSS', 'wpp' ),
    js: __( 'JavaScript', 'wpp' ),
    html: __( 'HTML', 'wpp' ),
    media: __( 'Media', 'wpp' ),
    database: __( 'Database', 'wpp' ),
    cdn: __( 'CDN', 'wpp' ),
    tools: __( 'Settings', 'wpp' ),
    cloudflare: __( 'Cloudflare', 'wpp' ),
    varnish: __( 'Varnish', 'wpp' ),
    prefetch: __( 'Prefetch', 'wpp' ),
};

export default function SettingsTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'tools' );
    const fileRef = useRef( null );
    const [ server, setServer ] = useState( null );
    const [ history, setHistory ] = useState( [] );
    const [ restoring, setRestoring ] = useState( -1 );
    const [ log, setLog ] = useState( null );
    const caps = ( window.WPP && window.WPP.caps ) || {};

    useEffect( () => {
        action( 'server' ).then( setServer ).catch( () => {} );
        action( 'tools/history' ).then( setHistory ).catch( () => {} );
    }, [] );

    const refreshLog = () => action( 'tools/log' ).then( setLog ).catch( () => {} );

    // Poll the log so the textarea stays current while the tab is open.
    useEffect( () => {
        refreshLog();
        const id = setInterval( refreshLog, 10000 );
        return () => clearInterval( id );
    }, [] );

    const handleSave = async () => {
        await save();
        refreshLog();
    };

    const clearLog = async () => {
        try {
            const next = await action( 'tools/log/clear', { method: 'POST' } );
            setLog( next );
            toast( __( 'Log cleared.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not clear the log.', 'wpp' ), 'error' );
        }
    };

    const restore = async ( index ) => {
        const ok = await confirm( {
            title: __( 'Restore settings', 'wpp' ),
            message: __( 'Your current settings will be replaced by this saved configuration.', 'wpp' ),
            confirm: __( 'Restore', 'wpp' ),
        } );
        if ( ! ok ) {
            return;
        }
        setRestoring( index );
        try {
            await action( 'tools/history/restore', { method: 'POST', data: { index } } );
            toast( __( 'Settings restored. Reloading…', 'wpp' ), 'success' );
            setTimeout( () => window.location.reload(), 800 );
        } catch ( e ) {
            toast( e.message || __( 'Could not restore settings.', 'wpp' ), 'error' );
            setRestoring( -1 );
        }
    };

    const exportSettings = async () => {
        try {
            const data = await action( 'tools/export' );
            const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
            const url = URL.createObjectURL( blob );
            const a = document.createElement( 'a' );
            a.href = url;
            a.download = 'wp-performance-settings.json';
            document.body.appendChild( a );
            a.click();
            a.remove();
            URL.revokeObjectURL( url );
        } catch ( e ) {
            toast( e.message || __( 'Export failed.', 'wpp' ), 'error' );
        }
    };

    const importSettings = async ( e ) => {
        const file = e.target.files && e.target.files[ 0 ];
        if ( ! file ) {
            return;
        }
        try {
            const data = JSON.parse( await file.text() );
            await action( 'tools/import', { method: 'POST', data } );
            toast( __( 'Settings imported. Reloading…', 'wpp' ), 'success' );
            setTimeout( () => window.location.reload(), 800 );
        } catch ( err ) {
            toast( err.message || __( 'Import failed - invalid file.', 'wpp' ), 'error' );
        } finally {
            if ( fileRef.current ) {
                fileRef.current.value = '';
            }
        }
    };

    const side = (
        <>
            <Section title={ __( 'Restore points', 'wpp' ) }>
                { history.length === 0 ? (
                    <p className="wpp-field__help">
                        { __(
                            'The last five saved configurations appear here so you can roll back a change.',
                            'wpp'
                        ) }
                    </p>
                ) : (
                    <ul className="wpp-history">
                        { history.map( ( h ) => (
                            <li key={ h.index } className="wpp-history__item">
                                <div className="wpp-history__meta">
                                    <strong>{ timeAgo( h.time ) }</strong>
                                    { h.trigger && GROUP_LABELS[ h.trigger ] && (
                                        <span>
                                            { sprintf(
                                                /* translators: %s: settings group name. */
                                                __( '%s settings', 'wpp' ),
                                                GROUP_LABELS[ h.trigger ]
                                            ) }
                                        </span>
                                    ) }
                                </div>
                                <button
                                    type="button"
                                    className="wpp-btn wpp-btn--ghost wpp-btn--sm"
                                    disabled={ restoring !== -1 }
                                    onClick={ () => restore( h.index ) }
                                >
                                    <RotateCcw size={ 14 } />
                                    { restoring === h.index
                                        ? __( 'Restoring…', 'wpp' )
                                        : __( 'Restore', 'wpp' ) }
                                </button>
                            </li>
                        ) ) }
                    </ul>
                ) }
            </Section>

            <Section title={ __( 'About', 'wpp' ) }>
                <p className="wpp-field__help">
                    { __(
                        'Export your configuration to move it between sites, or import a saved configuration file.',
                        'wpp'
                    ) }
                </p>
            </Section>
        </>
    );

    return (
        <TwoColumn side={ side }>
            <Section title={ __( 'Import / Export', 'wpp' ) }>
                <Field label={ __( 'Export settings', 'wpp' ) }>
                    <button type="button" className="wpp-btn wpp-btn--ghost" onClick={ exportSettings }>
                        { __( 'Download settings file', 'wpp' ) }
                    </button>
                </Field>
                <Field label={ __( 'Import settings', 'wpp' ) }>
                    <input
                        ref={ fileRef }
                        type="file"
                        accept="application/json,.json"
                        onChange={ importSettings }
                    />
                </Field>
            </Section>

            { server && server.type === 'nginx' && (
                <Section title={ __( 'Nginx rules', 'wpp' ) }>
                    <p className="wpp-field__help">
                        { __( 'Add these rules to your nginx server block, then reload nginx.', 'wpp' ) }
                    </p>
                    <textarea className="wpp-input wpp-textarea" readOnly rows={ 10 } value={ server.nginx } />
                </Section>
            ) }
            { server && server.type === 'apache' && (
                <Section title={ __( 'Server', 'wpp' ) }>
                    <p className="wpp-field__help">
                        { __( 'Browser cache and gzip rules are written to .htaccess automatically.', 'wpp' ) }
                    </p>
                </Section>
            ) }

            <Section title={ __( 'Object cache', 'wpp' ) }>
                { caps.redis ? (
                    <Field label={ __( 'Redis object cache', 'wpp' ) }>
                        <Toggle
                            checked={ draft.object_cache }
                            onChange={ ( v ) => set( 'object_cache', v ) }
                            label={ __( 'Enable persistent object cache', 'wpp' ) }
                            help={ __(
                                'Stores WordPress query and option lookups in Redis to cut database load. Installs an object-cache.php drop-in.',
                                'wpp'
                            ) }
                        />
                    </Field>
                ) : (
                    <p className="wpp-field__help">
                        { __(
                            'No supported object cache backend was detected. Install and enable the Redis PHP extension to use a persistent object cache.',
                            'wpp'
                        ) }
                    </p>
                ) }
            </Section>

            <Section title={ __( 'Troubleshooting', 'wpp' ) }>
                <Field label={ __( 'Logging', 'wpp' ) }>
                    <Toggle
                        checked={ draft.enable_log }
                        onChange={ ( v ) => set( 'enable_log', v ) }
                        label={ __( 'Enable troubleshooting log', 'wpp' ) }
                        help={ __( 'Records cache and optimization activity to help diagnose issues.', 'wpp' ) }
                    />
                </Field>

                { log && log.enabled && (
                    <Field label={ __( 'Log file', 'wpp' ) }>
                        <textarea
                            className="wpp-input wpp-textarea wpp-log"
                            readOnly
                            rows={ 14 }
                            value={ log.content || __( 'The log is empty.', 'wpp' ) }
                        />
                        <div className="wpp-log__actions">
                            <p className="wpp-field__help">
                                { __( 'Content refreshes automatically every 10 seconds.', 'wpp' ) }
                            </p>
                            <button type="button" className="wpp-btn wpp-btn--ghost wpp-btn--sm" onClick={ clearLog }>
                                { __( 'Clear log', 'wpp' ) }
                            </button>
                        </div>
                    </Field>
                ) }
            </Section>

            <SaveButton onSave={ handleSave } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

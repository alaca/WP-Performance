import { useState } from '@wordpress/element';
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
import { action } from '../api';
import { toast } from '../lib/toast';

const CACHE_LEVELS = [
    { value: 'aggressive', label: __( 'Aggressive', 'wpp' ) },
    { value: 'simplified', label: __( 'Simplified (ignore query string)', 'wpp' ) },
    { value: 'basic', label: __( 'Basic (no query string)', 'wpp' ) },
];

const TTLS = [
    0, 1800, 3600, 7200, 14400, 18000, 28800, 43200, 57600, 72000, 86400,
    172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 2678400, 5356800, 16070400, 31536000,
];

export default function CloudflareTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'cloudflare' );
    const [ busy, setBusy ] = useState( '' );

    const purge = async ( path, label, data ) => {
        setBusy( path );
        try {
            await action( `cloudflare/${ path }`, { method: 'POST', data } );
            toast( `${ label } ${ __( 'complete.', 'wpp' ) }`, 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Cloudflare error.', 'wpp' ), 'error' );
        } finally {
            setBusy( '' );
        }
    };

    // Zone settings are pushed while the save request runs, so the outcome is
    // only readable afterwards.
    const saveAndReport = async () => {
        const next = await save().catch( () => null );
        if ( ! next ) {
            return;
        }
        const status = await action( 'cloudflare/status' ).catch( () => null );
        const failed = status && status.errors ? Object.keys( status.errors ) : [];
        if ( failed.length ) {
            toast( `${ __( 'Cloudflare rejected:', 'wpp' ) } ${ failed.join( ', ' ) }`, 'error' );
        }
    };

    const ttlOptions = TTLS.map( ( t ) => ( {
        value: String( t ),
        label: t === 0 ? __( 'Respect existing headers', 'wpp' ) : `${ t }s`,
    } ) );

    const side = (
        <Section title={ __( 'Credentials', 'wpp' ) }>
            <Field label={ __( 'Account email', 'wpp' ) }>
                <TextField type="email" value={ draft.email } onChange={ ( v ) => set( 'email', v ) } />
            </Field>
            <Field label={ __( 'API key', 'wpp' ) }>
                <TextField value={ draft.api_key } onChange={ ( v ) => set( 'api_key', v ) } />
            </Field>
            <Field label={ __( 'Zone ID', 'wpp' ) }>
                <TextField value={ draft.zone_id } onChange={ ( v ) => set( 'zone_id', v ) } />
            </Field>
        </Section>
    );

    return (
        <TwoColumn side={ side }>
            <Section title={ __( 'Purge cache', 'wpp' ) }>
                <div className="wpp-inline">
                    <button
                        type="button"
                        className="wpp-btn wpp-btn--ghost"
                        disabled={ busy === 'purge' }
                        onClick={ () => purge( 'purge', __( 'Purge everything', 'wpp' ) ) }
                    >
                        { busy === 'purge' ? __( 'Purging…', 'wpp' ) : __( 'Purge everything', 'wpp' ) }
                    </button>
                    <button
                        type="button"
                        className="wpp-btn wpp-btn--ghost"
                        disabled={ busy === 'purge-custom' }
                        onClick={ () =>
                            purge( 'purge-custom', __( 'Purge custom URLs', 'wpp' ), {
                                urls: draft.custom_purge_urls || [],
                            } )
                        }
                    >
                        { __( 'Purge custom URLs', 'wpp' ) }
                    </button>
                </div>
                <Field label={ __( 'Custom URLs to purge', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.custom_purge_urls }
                        onChange={ ( v ) => set( 'custom_purge_urls', v ) }
                        addLabel={ __( 'Add URL', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Zone settings', 'wpp' ) }>
                <Field label={ __( 'Development mode', 'wpp' ) }>
                    <Toggle
                        checked={ draft.dev_mode }
                        onChange={ ( v ) => set( 'dev_mode', v ) }
                        label={ __( 'Temporarily bypass the Cloudflare cache (auto-off after 3h)', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Cache level', 'wpp' ) }>
                    <SelectField value={ draft.cache_level } options={ CACHE_LEVELS } onChange={ ( v ) => set( 'cache_level', v ) } />
                </Field>
                <Field label={ __( 'Browser cache TTL', 'wpp' ) }>
                    <SelectField
                        value={ String( draft.browser_expire ) }
                        options={ ttlOptions }
                        onChange={ ( v ) => set( 'browser_expire', Number( v ) ) }
                    />
                </Field>
                <Field label={ __( 'Rocket Loader', 'wpp' ) }>
                    <Toggle checked={ draft.rocket_loader } onChange={ ( v ) => set( 'rocket_loader', v ) } label={ __( 'Asynchronous JavaScript loading', 'wpp' ) } />
                </Field>
                <Field label={ __( 'Brotli', 'wpp' ) }>
                    <Toggle checked={ draft.brotli } onChange={ ( v ) => set( 'brotli', v ) } label={ __( 'Brotli compression', 'wpp' ) } />
                </Field>
            </Section>

            <SaveButton onSave={ saveAndReport } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

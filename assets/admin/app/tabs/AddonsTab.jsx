import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import TextField from '../components/fields/TextField';
import SelectField from '../components/fields/SelectField';
import RepeatableInput from '../components/fields/RepeatableInput';

const config = window.WPP || {};

const PREFETCH_MODES = [
    { value: 'prerender', label: __( 'Prerender (load the whole page, instant)', 'wpp' ) },
    { value: 'prefetch', label: __( 'Prefetch (fetch the page, lighter)', 'wpp' ) },
];

const PREFETCH_EAGERNESS = [
    { value: 'conservative', label: __( 'On click', 'wpp' ) },
    { value: 'moderate', label: __( 'On hover', 'wpp' ) },
    { value: 'eager', label: __( 'As soon as possible', 'wpp' ) },
];

export default function AddonsTab() {
    const cf = useSettingsGroup( 'cloudflare' );
    const varnish = useSettingsGroup( 'varnish' );
    const prefetch = useSettingsGroup( 'prefetch' );

    const dirty = cf.isDirty || varnish.isDirty || prefetch.isDirty;
    const saving = cf.saving || varnish.saving || prefetch.saving;

    // The Cloudflare tab only exists once 'enabled' is persisted, so the draft
    // has to be saved before the navigation can land anywhere.
    const configureCloudflare = async () => {
        if ( cf.isDirty ) {
            const ok = await cf.save().then( () => true, () => false );
            if ( ! ok ) {
                return;
            }
        }
        window.location.href = `${ config.adminUrl || '' }&tab=cloudflare`;
    };

    const saveAll = async () => {
        if ( cf.isDirty ) {
            await cf.save();
        }
        if ( varnish.isDirty ) {
            await varnish.save();
        }
        if ( prefetch.isDirty ) {
            await prefetch.save();
        }
    };

    return (
        <TwoColumn>
            <div className="wpp-addons">
                <div className="wpp-addon">
                    <div className="wpp-addon__head">
                        <h3>{ __( 'Cloudflare', 'wpp' ) }</h3>
                        <Toggle checked={ cf.draft.enabled } onChange={ ( v ) => cf.set( 'enabled', v ) } />
                    </div>
                    <p className="wpp-addon__desc">
                        { __( 'Integrate your Cloudflare account to purge cache and manage zone settings.', 'wpp' ) }
                    </p>
                    { cf.draft.enabled && (
                        <button
                            type="button"
                            className="wpp-btn wpp-btn--ghost"
                            disabled={ cf.saving }
                            onClick={ configureCloudflare }
                        >
                            { __( 'Configure Cloudflare', 'wpp' ) }
                        </button>
                    ) }
                </div>

                <div className="wpp-addon">
                    <div className="wpp-addon__head">
                        <h3>{ __( 'Varnish', 'wpp' ) }</h3>
                        <Toggle checked={ varnish.draft.enabled } onChange={ ( v ) => varnish.set( 'enabled', v ) } />
                    </div>
                    <p className="wpp-addon__desc">
                        { __( 'Automatically purge Varnish whenever the cache is cleared.', 'wpp' ) }
                    </p>
                    { varnish.draft.enabled && (
                        <div className="wpp-addon__field">
                            <label>{ __( 'Custom host', 'wpp' ) }</label>
                            <TextField
                                value={ varnish.draft.custom_host }
                                onChange={ ( v ) => varnish.set( 'custom_host', v ) }
                                placeholder="http://127.0.0.1"
                            />
                        </div>
                    ) }
                </div>

                <div className="wpp-addon">
                    <div className="wpp-addon__head">
                        <h3>{ __( 'Link prefetch', 'wpp' ) }</h3>
                        <Toggle checked={ prefetch.draft.enabled } onChange={ ( v ) => prefetch.set( 'enabled', v ) } />
                    </div>
                    <p className="wpp-addon__desc">
                        { __( 'Loads the next page in the background before a visitor clicks, so navigation feels instant. Uses the Speculation Rules API.', 'wpp' ) }
                    </p>
                    { prefetch.draft.enabled && (
                        <>
                            <div className="wpp-addon__field">
                                <label>{ __( 'Mode', 'wpp' ) }</label>
                                <SelectField
                                    value={ prefetch.draft.mode }
                                    options={ PREFETCH_MODES }
                                    onChange={ ( v ) => prefetch.set( 'mode', v ) }
                                />
                            </div>
                            <div className="wpp-addon__field">
                                <label>{ __( 'When to start', 'wpp' ) }</label>
                                <SelectField
                                    value={ prefetch.draft.eagerness }
                                    options={ PREFETCH_EAGERNESS }
                                    onChange={ ( v ) => prefetch.set( 'eagerness', v ) }
                                />
                            </div>
                            <div className="wpp-addon__field">
                                <label>{ __( 'Exclude URLs', 'wpp' ) }</label>
                                <RepeatableInput
                                    value={ prefetch.draft.exclude }
                                    onChange={ ( v ) => prefetch.set( 'exclude', v ) }
                                    placeholder="/cart/*"
                                    addLabel={ __( 'Add URL', 'wpp' ) }
                                />
                            </div>
                        </>
                    ) }
                </div>
            </div>

            <SaveButton onSave={ saveAll } isDirty={ dirty } saving={ saving } />
        </TwoColumn>
    );
}

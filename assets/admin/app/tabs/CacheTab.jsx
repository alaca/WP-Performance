import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import NumberField from '../components/fields/NumberField';
import SelectField from '../components/fields/SelectField';
import RepeatableInput from '../components/fields/RepeatableInput';
import { action } from '../api';
import { toast } from '../lib/toast';
import { formatBytes } from '../lib/format';

const config = window.WPP || {};

const UNITS = [
    { value: '60', label: __( 'Minutes', 'wpp' ) },
    { value: '3600', label: __( 'Hours', 'wpp' ) },
    { value: '86400', label: __( 'Days', 'wpp' ) },
];

const WILDCARD_HELP = __(
    'Use {numbers}, {letters}, {any} or {all} to match dynamic parts of a URL.',
    'wpp'
);

export default function CacheTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'cache' );
    const on = !! draft.enabled;

    const [ stats, setStats ] = useState( null );
    const [ clearing, setClearing ] = useState( false );
    const [ clearingFragments, setClearingFragments ] = useState( false );

    useEffect( () => {
        action( 'cache/stats' ).then( setStats ).catch( () => {} );
    }, [] );

    const clearFragments = async () => {
        setClearingFragments( true );
        try {
            await action( 'cache/fragments/clear', { method: 'POST' } );
            toast( __( 'Block cache cleared.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not clear the block cache.', 'wpp' ), 'error' );
        } finally {
            setClearingFragments( false );
        }
    };

    const clearCache = async () => {
        setClearing( true );
        try {
            const s = await action( 'cache/clear', { method: 'POST' } );
            setStats( s );
            toast( __( 'Cache cleared.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not clear the cache.', 'wpp' ), 'error' );
        } finally {
            setClearing( false );
        }
    };

    const statRows = [
        [ __( 'HTML files', 'wpp' ), stats && stats.html ],
        [ __( 'CSS files', 'wpp' ), stats && stats.css ],
        [ __( 'JavaScript files', 'wpp' ), stats && stats.js ],
    ];

    const side = (
        <>
            <Section title={ __( 'Cache statistics', 'wpp' ) }>
                <ul className="wpp-stats">
                    { statRows.map( ( [ label, value ] ) => (
                        <li key={ label }>
                            <span>{ label }</span>
                            <strong>{ stats ? formatBytes( value ) : '-' }</strong>
                        </li>
                    ) ) }
                    <li className="wpp-stats__total">
                        <span>{ __( 'Total', 'wpp' ) }</span>
                        <strong>{ stats ? formatBytes( stats.total ) : '-' }</strong>
                    </li>
                </ul>
                <button
                    type="button"
                    className="wpp-btn wpp-btn--ghost"
                    disabled={ clearing }
                    onClick={ clearCache }
                >
                    { clearing ? __( 'Clearing…', 'wpp' ) : __( 'Clear cache', 'wpp' ) }
                </button>
            </Section>

            <Section title={ __( 'Exclude URL(s) from cache', 'wpp' ) }>
                <RepeatableInput
                    value={ draft.exclude_urls }
                    onChange={ ( v ) => set( 'exclude_urls', v ) }
                    placeholder={ config.siteUrl || '' }
                    addLabel={ __( 'Add URL', 'wpp' ) }
                />
                <p className="wpp-field__help">{ WILDCARD_HELP }</p>
            </Section>

            <Section title={ __( 'Exclude User Agent(s) from cache', 'wpp' ) }>
                <RepeatableInput
                    value={ draft.exclude_user_agents }
                    onChange={ ( v ) => set( 'exclude_user_agents', v ) }
                    addLabel={ __( 'Add User Agent', 'wpp' ) }
                />
                <div className="wpp-stack-top">
                    <Toggle
                        checked={ draft.exclude_search_bots }
                        onChange={ ( v ) => set( 'exclude_search_bots', v ) }
                        label={ __( 'Exclude search engines', 'wpp' ) }
                        help={ __( 'Google, Bing and other crawlers bypass the cache.', 'wpp' ) }
                    />
                </div>
            </Section>
        </>
    );

    return (
        <TwoColumn side={ side }>
            <Section>
                <Field label={ __( 'Enable cache', 'wpp' ) }>
                    <Toggle
                        checked={ on }
                        onChange={ ( v ) => set( 'enabled', v ) }
                        help={ __(
                            'Reduces server response time by serving static HTML files to visitors.',
                            'wpp'
                        ) }
                    />
                </Field>

                { on && (
                    <>
                        <Field label={ __( 'Mobile cache', 'wpp' ) }>
                            <Toggle
                                checked={ draft.mobile }
                                onChange={ ( v ) => set( 'mobile', v ) }
                                label={ __( 'Separate cache for mobile devices', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Clear cache after', 'wpp' ) }>
                            <div className="wpp-inline">
                                <NumberField
                                    value={ draft.clear_time }
                                    min={ 1 }
                                    onChange={ ( v ) => set( 'clear_time', v === '' ? 1 : v ) }
                                />
                                <SelectField
                                    value={ String( draft.clear_unit ) }
                                    options={ UNITS }
                                    onChange={ ( v ) => set( 'clear_unit', Number( v ) ) }
                                />
                            </div>
                        </Field>

                        <Field label={ __( 'Clear cache when', 'wpp' ) }>
                            <Toggle
                                checked={ draft.clear_on_publish }
                                onChange={ ( v ) => set( 'clear_on_publish', v ) }
                                label={ __( 'A post or page is published or updated', 'wpp' ) }
                            />
                            <Toggle
                                checked={ draft.clear_on_delete }
                                onChange={ ( v ) => set( 'clear_on_delete', v ) }
                                label={ __( 'A post or page is deleted', 'wpp' ) }
                            />
                            <Toggle
                                checked={ draft.clear_on_save }
                                onChange={ ( v ) => set( 'clear_on_save', v ) }
                                label={ __( 'WP Performance settings are saved', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Clear cache options', 'wpp' ) }>
                            <Toggle
                                checked={ draft.keep_assets }
                                onChange={ ( v ) => set( 'keep_assets', v ) }
                                label={ __( 'Keep generated CSS/JS files when clearing the cache', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Query strings', 'wpp' ) }>
                            <Toggle
                                checked={ draft.cache_query_strings }
                                onChange={ ( v ) => set( 'cache_query_strings', v ) }
                                label={ __( 'Cache URLs with query strings', 'wpp' ) }
                                help={ __(
                                    'Off (recommended): tracking params (utm, gclid, fbclid…) are ignored so only clean URLs are cached.',
                                    'wpp'
                                ) }
                            />
                        </Field>

                        <Field
                            label={ __( 'Cache preloading', 'wpp' ) }
                            help={ __(
                                'Enter the path to an XML sitemap used to preload the cache.',
                                'wpp'
                            ) }
                        >
                            <RepeatableInput
                                value={ draft.sitemaps }
                                onChange={ ( v ) => set( 'sitemaps', v ) }
                                placeholder={ `${ config.siteUrl || '' }sitemap.xml` }
                                addLabel={ __( 'Add Sitemap', 'wpp' ) }
                            />
                        </Field>
                    </>
                ) }
            </Section>

            <Section title={ __( 'Browser caching', 'wpp' ) }>
                <Field label={ __( 'Leverage browser caching', 'wpp' ) }>
                    <Toggle
                        checked={ draft.browser_cache }
                        onChange={ ( v ) => set( 'browser_cache', v ) }
                        help={ __(
                            'Sets HTTP expiry headers so returning visitors load resources from their browser.',
                            'wpp'
                        ) }
                    />
                </Field>
                <Field label={ __( 'Gzip compression', 'wpp' ) }>
                    <Toggle
                        checked={ draft.gzip }
                        onChange={ ( v ) => set( 'gzip', v ) }
                        help={ __( 'Compresses resources before sending them to the browser.', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Block cache', 'wpp' ) }>
                <Field label={ __( 'Enable block cache', 'wpp' ) }>
                    <Toggle
                        checked={ draft.block_cache_enabled }
                        onChange={ ( v ) => set( 'block_cache_enabled', v ) }
                        help={ __(
                            'Caches parts of a page so they load faster, even when the full page is not cached. Add a Cache block to a page to use it.',
                            'wpp'
                        ) }
                    />
                </Field>

                { draft.block_cache_enabled && (
                    <>
                        <Field label={ __( 'Default cache time (seconds)', 'wpp' ) }>
                            <NumberField
                                value={ draft.block_cache_ttl }
                                min={ 1 }
                                onChange={ ( v ) => set( 'block_cache_ttl', v === '' ? 1 : v ) }
                            />
                        </Field>

                        <Field label={ __( 'Clearing', 'wpp' ) }>
                            <Toggle
                                checked={ draft.block_cache_flush_on_clear }
                                onChange={ ( v ) => set( 'block_cache_flush_on_clear', v ) }
                                label={ __( 'Also clear it when I clear the page cache', 'wpp' ) }
                            />
                        </Field>

                        <Field label={ __( 'Block cache', 'wpp' ) }>
                            <button
                                type="button"
                                className="wpp-btn wpp-btn--ghost"
                                disabled={ clearingFragments }
                                onClick={ clearFragments }
                            >
                                { clearingFragments ? __( 'Clearing…', 'wpp' ) : __( 'Clear block cache', 'wpp' ) }
                            </button>
                        </Field>
                    </>
                ) }
            </Section>

            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

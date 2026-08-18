import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { confirm } from '../lib/confirm';
import { CheckCircle2, AlertTriangle, XCircle, Check, Minus } from 'lucide-react';
import Section from '../components/Section';
import { action } from '../api';
import { toast } from '../lib/toast';
import { formatBytes } from '../lib/format';

const STATUS_ICON = {
    good: CheckCircle2,
    warn: AlertTriangle,
    bad: XCircle,
};

const PRESETS = [
    {
        name: 'safe',
        label: __( 'Safe', 'wpp' ),
        description: __(
            'Page cache, GZIP, browser caching and lazy loading. Works with virtually any theme.',
            'wpp'
        ),
    },
    {
        name: 'balanced',
        label: __( 'Balanced', 'wpp' ),
        recommended: true,
        description: __(
            'Adds deferred CSS/JS, hosted fonts and modern image formats. Recommended for most sites.',
            'wpp'
        ),
    },
    {
        name: 'aggressive',
        label: __( 'Aggressive', 'wpp' ),
        description: __(
            'Every optimization on, including delayed JavaScript and unused-CSS removal. Test your site after applying.',
            'wpp'
        ),
    },
];

export default function OverviewTab() {
    const [ data, setData ] = useState( null );
    const [ clearing, setClearing ] = useState( false );
    const [ applying, setApplying ] = useState( '' );

    const load = () => action( 'overview' ).then( setData ).catch( () => {} );

    useEffect( () => {
        load();
    }, [] );

    const clearCache = async () => {
        setClearing( true );
        try {
            await action( 'cache/clear', { method: 'POST' } );
            await load();
            toast( __( 'Cache cleared.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not clear the cache.', 'wpp' ), 'error' );
        } finally {
            setClearing( false );
        }
    };

    const applyPreset = async ( name ) => {
        const ok = await confirm( {
            title: __( 'Apply preset', 'wpp' ),
            message: __( 'This replaces the settings the preset covers. Your file lists, exclusions and CDN settings are kept.', 'wpp' ),
            confirm: __( 'Apply', 'wpp' ),
        } );
        if ( ! ok ) {
            return;
        }
        setApplying( name );
        try {
            await action( 'tools/preset', { method: 'POST', data: { name } } );
            toast( __( 'Preset applied. Reloading…', 'wpp' ), 'success' );
            setTimeout( () => window.location.reload(), 800 );
        } catch ( e ) {
            toast( e.message || __( 'Could not apply the preset.', 'wpp' ), 'error' );
            setApplying( '' );
        }
    };

    const cache = data && data.cache;
    const features = ( data && data.features ) || [];
    const health = ( data && data.health ) || [];

    return (
        <div className="wpp-overview">
            <div className="wpp-overview__cards">
                <div className="wpp-stat-card">
                    <span className="wpp-stat-card__label">{ __( 'Cache status', 'wpp' ) }</span>
                    <strong className="wpp-stat-card__value">
                        { cache
                            ? cache.enabled
                                ? __( 'Active', 'wpp' )
                                : __( 'Disabled', 'wpp' )
                            : '-' }
                    </strong>
                </div>
                <div className="wpp-stat-card">
                    <span className="wpp-stat-card__label">{ __( 'Cached pages', 'wpp' ) }</span>
                    <strong className="wpp-stat-card__value">
                        { cache ? cache.pages.toLocaleString() : '-' }
                    </strong>
                </div>
                <div className="wpp-stat-card">
                    <span className="wpp-stat-card__label">{ __( 'Cache size', 'wpp' ) }</span>
                    <strong className="wpp-stat-card__value">
                        { cache ? formatBytes( cache.bytes ) : '-' }
                    </strong>
                </div>
                { cache && cache.blocks && cache.blocks.enabled && (
                    <div className="wpp-stat-card">
                        <span className="wpp-stat-card__label">{ __( 'Block fragments', 'wpp' ) }</span>
                        <strong className="wpp-stat-card__value">
                            { cache.blocks.backend === 'object'
                                ? __( 'In Redis', 'wpp' )
                                : cache.blocks.count.toLocaleString() }
                        </strong>
                    </div>
                ) }
                <div className="wpp-stat-card wpp-stat-card--action">
                    <button
                        type="button"
                        className="wpp-btn wpp-btn--ghost"
                        disabled={ clearing }
                        onClick={ clearCache }
                    >
                        { clearing ? __( 'Clearing…', 'wpp' ) : __( 'Clear cache', 'wpp' ) }
                    </button>
                </div>
            </div>

            <Section title={ __( 'Quick setup', 'wpp' ) }>
                <p className="wpp-field__help">
                    { __(
                        'Apply a curated configuration in one click, then fine-tune any tab.',
                        'wpp'
                    ) }
                </p>
                <div className="wpp-presets">
                    { PRESETS.map( ( p ) => (
                        <div key={ p.name } className="wpp-preset">
                            <div className="wpp-preset__head">
                                <strong>{ p.label }</strong>
                                { p.recommended && (
                                    <span className="wpp-preset__badge">
                                        { __( 'Recommended', 'wpp' ) }
                                    </span>
                                ) }
                            </div>
                            <p className="wpp-preset__desc">{ p.description }</p>
                            <button
                                type="button"
                                className="wpp-btn wpp-btn--ghost"
                                disabled={ !! applying }
                                onClick={ () => applyPreset( p.name ) }
                            >
                                { applying === p.name
                                    ? __( 'Applying…', 'wpp' )
                                    : __( 'Apply', 'wpp' ) }
                            </button>
                        </div>
                    ) ) }
                </div>
            </Section>

            <Section title={ __( 'Active optimizations', 'wpp' ) }>
                <ul className="wpp-feature-grid">
                    { features.map( ( f ) => (
                        <li
                            key={ f.label }
                            className={ `wpp-feature ${ f.enabled ? 'is-on' : 'is-off' }` }
                        >
                            <span className="wpp-feature__icon">
                                { f.enabled ? <Check size={ 16 } /> : <Minus size={ 16 } /> }
                            </span>
                            <span className="wpp-feature__label">{ f.label }</span>
                        </li>
                    ) ) }
                </ul>
            </Section>

            <Section title={ __( 'Health checks', 'wpp' ) }>
                <ul className="wpp-health">
                    { health.map( ( c ) => {
                        const Icon = STATUS_ICON[ c.status ] || AlertTriangle;
                        return (
                            <li key={ c.id } className={ `wpp-health__item is-${ c.status }` }>
                                <span className="wpp-health__icon">
                                    <Icon size={ 18 } />
                                </span>
                                <div className="wpp-health__text">
                                    <strong>{ c.label }</strong>
                                    <span>{ c.message }</span>
                                </div>
                            </li>
                        );
                    } ) }
                </ul>
            </Section>
        </div>
    );
}

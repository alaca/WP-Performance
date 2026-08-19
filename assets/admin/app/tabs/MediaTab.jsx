import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { confirm } from '../lib/confirm';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import NumberField from '../components/fields/NumberField';
import RepeatableInput from '../components/fields/RepeatableInput';
import ImageSizes from '../components/ImageSizes';
import { action } from '../api';
import { toast } from '../lib/toast';

const config = window.WPP || {};

export default function MediaTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'media' );
    const lazy = !! draft.images_lazy;
    const [ converting, setConverting ] = useState( null );

    const convert = async () => {
        // eslint-disable-next-line no-alert
        const ok = await confirm( {
            title: __( 'Convert images', 'wpp' ),
            message: __( 'Convert all images to WebP and AVIF? This can take a while on large media libraries.', 'wpp' ),
            confirm: __( 'Convert', 'wpp' ),
        } );
        if ( ! ok ) {
            return;
        }
        let offset = 0;
        setConverting( { processed: 0, total: 0 } );
        try {
            for ( ;; ) {
                const r = await action( 'images/convert', { method: 'POST', data: { offset, limit: 5 } } );
                setConverting( { processed: r.processed, total: r.total } );
                offset = r.processed;
                if ( r.done ) {
                    break;
                }
            }
            toast( __( 'Images converted.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Conversion failed.', 'wpp' ), 'error' );
        } finally {
            setTimeout( () => setConverting( null ), 1500 );
        }
    };

    const side = (
        <>
            <Section title={ __( 'Emoji & Embeds', 'wpp' ) }>
                <Field label={ __( 'Emoji', 'wpp' ) }>
                    <Toggle
                        checked={ draft.disable_emoji }
                        onChange={ ( v ) => set( 'disable_emoji', v ) }
                        label={ __( 'Disable WordPress emoji', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Embeds', 'wpp' ) }>
                    <Toggle
                        checked={ draft.disable_embeds }
                        onChange={ ( v ) => set( 'disable_embeds', v ) }
                        label={ __( 'Disable oEmbed discovery', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Image sizes', 'wpp' ) }>
                <ImageSizes />
            </Section>
        </>
    );

    return (
        <TwoColumn side={ side }>
            <Section title={ __( 'Images', 'wpp' ) }>
                <Field label={ __( 'Lazy load', 'wpp' ) }>
                    <Toggle
                        checked={ lazy }
                        onChange={ ( v ) => set( 'images_lazy', v ) }
                        label={ __( 'Lazy load images', 'wpp' ) }
                        help={ __( 'Defer offscreen images with native loading="lazy".', 'wpp' ) }
                    />
                    { lazy && (
                        <div className="wpp-stack-top">
                            <Toggle
                                checked={ draft.images_lazy_disable_mobile }
                                onChange={ ( v ) => set( 'images_lazy_disable_mobile', v ) }
                                label={ __( 'Disable lazy load on mobile devices', 'wpp' ) }
                            />
                        </div>
                    ) }
                </Field>
                <Field label={ __( 'Responsive images', 'wpp' ) }>
                    <Toggle
                        checked={ draft.images_responsive }
                        onChange={ ( v ) => set( 'images_responsive', v ) }
                        label={ __( 'Force srcset on images', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Image dimensions', 'wpp' ) }>
                    <Toggle
                        checked={ draft.images_dimensions }
                        onChange={ ( v ) => set( 'images_dimensions', v ) }
                        label={ __( 'Add missing width/height to prevent layout shift (CLS)', 'wpp' ) }
                    />
                </Field>
                <Field
                    label={ __( 'Preload LCP images', 'wpp' ) }
                    help={ __(
                        'Number of above-the-fold images to preload (fetchpriority=high) and skip from lazy load. 0 = off.',
                        'wpp'
                    ) }
                >
                    <NumberField
                        value={ draft.lcp_images }
                        min={ 0 }
                        max={ 5 }
                        onChange={ ( v ) => set( 'lcp_images', v === '' ? 0 : v ) }
                    />
                </Field>
                <Field
                    label={ __( 'Next-gen images', 'wpp' ) }
                    help={ __( 'Serve WebP/AVIF via <picture> when a converted file exists. New uploads convert automatically.', 'wpp' ) }
                >
                    <Toggle
                        checked={ draft.webp }
                        onChange={ ( v ) => set( 'webp', v ) }
                        label={ __( 'Serve WebP/AVIF images', 'wpp' ) }
                    />
                    <div className="wpp-stack-top">
                        <button
                            type="button"
                            className="wpp-btn wpp-btn--ghost"
                            disabled={ !! converting }
                            onClick={ convert }
                        >
                            { converting
                                ? `${ __( 'Converting', 'wpp' ) } ${ converting.total ? Math.round( ( converting.processed / converting.total ) * 100 ) : 0 }%`
                                : __( 'Convert existing images', 'wpp' ) }
                        </button>
                    </div>
                </Field>
                <Field label={ __( 'Exclude by name', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.images_exclude }
                        onChange={ ( v ) => set( 'images_exclude', v ) }
                        addLabel={ __( 'Add image', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Exclude containers', 'wpp' ) } help={ __( 'CSS id or class, e.g. #hero or .no-lazy', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.images_exclude_containers }
                        onChange={ ( v ) => set( 'images_exclude_containers', v ) }
                        addLabel={ __( 'Add container', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Exclude URL(s)', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.images_exclude_urls }
                        onChange={ ( v ) => set( 'images_exclude_urls', v ) }
                        placeholder={ config.siteUrl || '' }
                        addLabel={ __( 'Add URL', 'wpp' ) }
                    />
                </Field>
            </Section>

            <Section title={ __( 'Videos', 'wpp' ) }>
                <Field label={ __( 'Lazy load', 'wpp' ) }>
                    <Toggle
                        checked={ draft.videos_lazy }
                        onChange={ ( v ) => set( 'videos_lazy', v ) }
                        label={ __( 'Lazy load videos & iframes', 'wpp' ) }
                    />
                </Field>
                <Field label={ __( 'Exclude URL(s)', 'wpp' ) }>
                    <RepeatableInput
                        value={ draft.videos_exclude_urls }
                        onChange={ ( v ) => set( 'videos_exclude_urls', v ) }
                        addLabel={ __( 'Add URL', 'wpp' ) }
                    />
                </Field>
            </Section>

            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

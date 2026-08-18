import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'wpp/cache', {
    apiVersion: 2,
    title: __( 'Cache', 'wpp' ),
    description: __( 'Saves everything inside this block so it loads faster.', 'wpp' ),
    category: 'design',
    icon: 'database',
    keywords: [ __( 'cache', 'wpp' ), __( 'fragment', 'wpp' ), __( 'performance', 'wpp' ) ],
    supports: { html: false, anchor: true },
    attributes: {
        ttl: { type: 'number', default: 0 },
        varyUrl: { type: 'boolean', default: true },
        varyLoggedin: { type: 'boolean', default: false },
        varyRole: { type: 'boolean', default: false },
        varyDevice: { type: 'boolean', default: false },
    },

    edit( { attributes, setAttributes } ) {
        const blockProps = useBlockProps();

        return (
            <div { ...blockProps }>
                <InspectorControls>
                    <PanelBody title={ __( 'Caching', 'wpp' ) }>
                        <TextControl
                            type="number"
                            label={ __( 'Cache for (seconds)', 'wpp' ) }
                            help={ __( 'Leave 0 to use the default.', 'wpp' ) }
                            value={ String( attributes.ttl ) }
                            min={ 0 }
                            onChange={ ( v ) => setAttributes( { ttl: parseInt( v || '0', 10 ) || 0 } ) }
                        />
                        <ToggleControl
                            label={ __( 'Cache each page separately', 'wpp' ) }
                            help={ __( 'Turn off to reuse one copy everywhere, like a menu or footer.', 'wpp' ) }
                            checked={ attributes.varyUrl }
                            onChange={ ( v ) => setAttributes( { varyUrl: v } ) }
                        />
                        <ToggleControl
                            label={ __( 'Separate copy for logged-in visitors', 'wpp' ) }
                            checked={ attributes.varyLoggedin }
                            onChange={ ( v ) => setAttributes( { varyLoggedin: v } ) }
                        />
                        <ToggleControl
                            label={ __( 'Separate copy for each user role', 'wpp' ) }
                            checked={ attributes.varyRole }
                            onChange={ ( v ) => setAttributes( { varyRole: v } ) }
                        />
                        <ToggleControl
                            label={ __( 'Separate copy on mobile', 'wpp' ) }
                            checked={ attributes.varyDevice }
                            onChange={ ( v ) => setAttributes( { varyDevice: v } ) }
                        />
                    </PanelBody>
                </InspectorControls>
                <InnerBlocks />
            </div>
        );
    },

    save() {
        return <InnerBlocks.Content />;
    },
} );

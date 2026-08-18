import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { confirm } from '../lib/confirm';
import { action } from '../api';
import { toast } from '../lib/toast';

const EMPTY = { name: '', width: '', height: '', crop: false };

export default function ImageSizes() {
    const [ sizes, setSizes ] = useState( [] );
    const [ adding, setAdding ] = useState( false );
    const [ form, setForm ] = useState( EMPTY );
    const [ regen, setRegen ] = useState( null );

    useEffect( () => {
        action( 'images/sizes' ).then( setSizes ).catch( () => {} );
    }, [] );

    const add = async () => {
        if ( ! form.name || ! form.width || ! form.height ) {
            return;
        }
        try {
            const next = await action( 'images/sizes', {
                method: 'POST',
                data: {
                    name: form.name,
                    width: Number( form.width ),
                    height: Number( form.height ),
                    crop: form.crop,
                },
            } );
            setSizes( next );
            setForm( EMPTY );
            setAdding( false );
            toast( __( 'Image size added.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Could not add size.', 'wpp' ), 'error' );
        }
    };

    const remove = async ( name ) => {
        // eslint-disable-next-line no-alert
        const ok = await confirm( {
            title: __( 'Remove image size', 'wpp' ),
            message: __( 'New uploads will no longer be generated at this size.', 'wpp' ),
            confirm: __( 'Remove', 'wpp' ),
            destructive: true,
        } );
        if ( ! ok ) {
            return;
        }
        try {
            setSizes( await action( 'images/sizes/remove', { method: 'POST', data: { name } } ) );
        } catch ( e ) {
            toast( e.message || __( 'Could not remove size.', 'wpp' ), 'error' );
        }
    };

    const restore = async () => {
        // eslint-disable-next-line no-alert
        const ok = await confirm( {
            title: __( 'Restore defaults', 'wpp' ),
            message: __( 'This restores the WordPress default image sizes and discards your changes here.', 'wpp' ),
            confirm: __( 'Restore', 'wpp' ),
        } );
        if ( ! ok ) {
            return;
        }
        setSizes( await action( 'images/sizes/restore', { method: 'POST' } ) );
        toast( __( 'Defaults restored.', 'wpp' ), 'success' );
    };

    const regenerate = async () => {
        // eslint-disable-next-line no-alert
        const ok = await confirm( {
            title: __( 'Regenerate thumbnails', 'wpp' ),
            message: __( 'Every image is processed again. This may take a while on large media libraries.', 'wpp' ),
            confirm: __( 'Regenerate', 'wpp' ),
        } );
        if ( ! ok ) {
            return;
        }
        let offset = 0;
        setRegen( { processed: 0, total: 0 } );
        try {
            for ( ;; ) {
                const r = await action( 'images/regenerate', {
                    method: 'POST',
                    data: { offset, limit: 5 },
                } );
                setRegen( { processed: r.processed, total: r.total } );
                offset = r.processed;
                if ( r.done ) {
                    break;
                }
            }
            toast( __( 'Thumbnails regenerated.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Regeneration failed.', 'wpp' ), 'error' );
        } finally {
            setTimeout( () => setRegen( null ), 1500 );
        }
    };

    const pct = regen && regen.total ? Math.round( ( regen.processed / regen.total ) * 100 ) : 0;

    return (
        <div className="wpp-sizes">
            <table className="wpp-sizes__table">
                <thead>
                    <tr>
                        <th>{ __( 'Name', 'wpp' ) }</th>
                        <th>{ __( 'W', 'wpp' ) }</th>
                        <th>{ __( 'H', 'wpp' ) }</th>
                        <th>{ __( 'Crop', 'wpp' ) }</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    { sizes.map( ( s ) => (
                        <tr key={ s.name }>
                            <td>{ s.name }</td>
                            <td>{ s.width }</td>
                            <td>{ s.height }</td>
                            <td>{ s.crop ? __( 'Yes', 'wpp' ) : '-' }</td>
                            <td>
                                <button
                                    type="button"
                                    className="wpp-sizes__remove"
                                    aria-label={ __( 'Remove', 'wpp' ) }
                                    onClick={ () => remove( s.name ) }
                                >
                                    ×
                                </button>
                            </td>
                        </tr>
                    ) ) }
                </tbody>
            </table>

            { adding ? (
                <div className="wpp-sizes__form">
                    <input
                        className="wpp-input"
                        placeholder={ __( 'name', 'wpp' ) }
                        value={ form.name }
                        onChange={ ( e ) => setForm( { ...form, name: e.target.value } ) }
                    />
                    <input
                        className="wpp-input wpp-input--number"
                        type="number"
                        placeholder="W"
                        value={ form.width }
                        onChange={ ( e ) => setForm( { ...form, width: e.target.value } ) }
                    />
                    <input
                        className="wpp-input wpp-input--number"
                        type="number"
                        placeholder="H"
                        value={ form.height }
                        onChange={ ( e ) => setForm( { ...form, height: e.target.value } ) }
                    />
                    <label className="wpp-sizes__crop">
                        <input
                            type="checkbox"
                            checked={ form.crop }
                            onChange={ ( e ) => setForm( { ...form, crop: e.target.checked } ) }
                        />
                        { __( 'Crop', 'wpp' ) }
                    </label>
                    <button type="button" className="wpp-btn wpp-btn--primary" onClick={ add }>
                        { __( 'Add', 'wpp' ) }
                    </button>
                    <button type="button" className="wpp-btn wpp-btn--ghost" onClick={ () => setAdding( false ) }>
                        { __( 'Cancel', 'wpp' ) }
                    </button>
                </div>
            ) : (
                <div className="wpp-sizes__actions">
                    <button type="button" className="wpp-btn wpp-btn--ghost" onClick={ () => setAdding( true ) }>
                        { __( 'Add size', 'wpp' ) }
                    </button>
                    <button type="button" className="wpp-btn wpp-btn--ghost" onClick={ restore }>
                        { __( 'Restore defaults', 'wpp' ) }
                    </button>
                </div>
            ) }

            <div className="wpp-sizes__regen">
                <button type="button" className="wpp-btn wpp-btn--ghost" disabled={ !! regen } onClick={ regenerate }>
                    { regen
                        ? `${ __( 'Regenerating', 'wpp' ) } ${ pct }%`
                        : __( 'Regenerate thumbnails', 'wpp' ) }
                </button>
            </div>
        </div>
    );
}

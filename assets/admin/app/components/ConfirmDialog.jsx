import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { subscribe } from '../lib/confirm';

export default function ConfirmDialog() {
    const [ request, setRequest ] = useState( null );
    const dialogRef = useRef( null );
    const confirmRef = useRef( null );
    const openerRef = useRef( null );

    useEffect( () => subscribe( ( entry ) => {
        openerRef.current = document.activeElement;
        setRequest( entry );
    } ), [] );

    const close = ( answer ) => {
        if ( request ) {
            request.resolve( answer );
        }
        setRequest( null );

        // Send focus back where it came from, or the trigger silently loses it.
        if ( openerRef.current && typeof openerRef.current.focus === 'function' ) {
            openerRef.current.focus();
        }
        openerRef.current = null;
    };

    useEffect( () => {
        if ( ! request ) {
            return;
        }

        confirmRef.current?.focus();

        const onKeyDown = ( event ) => {
            if ( event.key === 'Escape' ) {
                event.preventDefault();
                close( false );
                return;
            }

            if ( event.key !== 'Tab' ) {
                return;
            }

            // Keep focus inside the dialog while it is open.
            const focusable = dialogRef.current?.querySelectorAll( 'button, [href], input, select, textarea' );
            if ( ! focusable || focusable.length === 0 ) {
                return;
            }
            const first = focusable[ 0 ];
            const last = focusable[ focusable.length - 1 ];

            if ( event.shiftKey && document.activeElement === first ) {
                event.preventDefault();
                last.focus();
            } else if ( ! event.shiftKey && document.activeElement === last ) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener( 'keydown', onKeyDown );
        return () => document.removeEventListener( 'keydown', onKeyDown );
    }, [ request ] );

    if ( ! request ) {
        return null;
    }

    const title = request.title || __( 'Are you sure?', 'wpp' );

    return (
        <div
            className="wpp-modal"
            onMouseDown={ ( e ) => {
                if ( e.target === e.currentTarget ) {
                    close( false );
                }
            } }
        >
            <div
                className="wpp-modal__box"
                role="dialog"
                aria-modal="true"
                aria-labelledby={ `wpp-modal-title-${ request.id }` }
                aria-describedby={ `wpp-modal-body-${ request.id }` }
                ref={ dialogRef }
            >
                <h2 className="wpp-modal__title" id={ `wpp-modal-title-${ request.id }` }>
                    { title }
                </h2>
                <p className="wpp-modal__body" id={ `wpp-modal-body-${ request.id }` }>
                    { request.message }
                </p>
                <div className="wpp-modal__actions">
                    <button
                        type="button"
                        className="wpp-btn wpp-btn--ghost"
                        onClick={ () => close( false ) }
                    >
                        { request.cancel || __( 'Cancel', 'wpp' ) }
                    </button>
                    <button
                        type="button"
                        className={ `wpp-btn ${ request.destructive ? 'wpp-btn--danger' : 'wpp-btn--primary' }` }
                        onClick={ () => close( true ) }
                        ref={ confirmRef }
                    >
                        { request.confirm || __( 'Confirm', 'wpp' ) }
                    </button>
                </div>
            </div>
        </div>
    );
}

import { __ } from '@wordpress/i18n';
import { Plus, X } from 'lucide-react';

/**
 * Edits an array of strings as a list of text inputs with add/remove.
 */
export default function RepeatableInput( {
    value = [],
    onChange,
    placeholder = '',
    addLabel = __( 'Add', 'wpp' ),
} ) {
    const rows = Array.isArray( value ) ? value : [];

    const update = ( idx, val ) => {
        const next = rows.slice();
        next[ idx ] = val;
        onChange( next );
    };
    const remove = ( idx ) => onChange( rows.filter( ( _, i ) => i !== idx ) );
    const add = () => onChange( [ ...rows, '' ] );

    return (
        <div className="wpp-repeatable">
            { rows.map( ( row, idx ) => (
                <div className="wpp-repeatable__row" key={ idx }>
                    <input
                        className="wpp-input"
                        type="text"
                        value={ row }
                        placeholder={ placeholder }
                        onChange={ ( e ) => update( idx, e.target.value ) }
                    />
                    <button
                        type="button"
                        className="wpp-repeatable__remove"
                        aria-label={ __( 'Remove', 'wpp' ) }
                        onClick={ () => remove( idx ) }
                    >
                        <X size={ 16 } />
                    </button>
                </div>
            ) ) }
            <button type="button" className="wpp-btn wpp-btn--ghost wpp-repeatable__add" onClick={ add }>
                <Plus size={ 16 } /> { addLabel }
            </button>
        </div>
    );
}

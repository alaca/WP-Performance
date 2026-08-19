import { __ } from '@wordpress/i18n';

export default function Placeholder( { label } ) {
    return (
        <div className="wpp-placeholder">
            <p>
                <strong>{ label }</strong>{ ' ' }
                { __( 'is being rebuilt in the next step.', 'wpp' ) }
            </p>
        </div>
    );
}

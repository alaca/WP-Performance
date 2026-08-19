import { __ } from '@wordpress/i18n';

export default function SaveButton( { onSave, isDirty, saving } ) {
    return (
        <div className="wpp-savebar">
            <button
                type="button"
                className="wpp-btn wpp-btn--primary"
                disabled={ ! isDirty || saving }
                onClick={ onSave }
            >
                { saving ? __( 'Saving…', 'wpp' ) : __( 'Save changes', 'wpp' ) }
            </button>
        </div>
    );
}

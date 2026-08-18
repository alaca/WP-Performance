export default function Toggle( { checked, onChange, label, help, disabled = false } ) {
    return (
        <label className={ `wpp-toggle ${ disabled ? 'is-disabled' : '' }` }>
            <span className="wpp-toggle__switch">
                <input
                    type="checkbox"
                    checked={ !! checked }
                    disabled={ disabled }
                    onChange={ ( e ) => onChange( e.target.checked ) }
                />
                <span className="wpp-toggle__track" aria-hidden="true">
                    <span className="wpp-toggle__thumb" />
                </span>
            </span>
            { ( label || help ) && (
                <span className="wpp-toggle__text">
                    { label && <span className="wpp-toggle__label">{ label }</span> }
                    { help && <span className="wpp-toggle__help">{ help }</span> }
                </span>
            ) }
        </label>
    );
}

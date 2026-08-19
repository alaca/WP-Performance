export default function Field( { label, children, help } ) {
    return (
        <div className="wpp-field">
            { label && <div className="wpp-field__label">{ label }</div> }
            <div className="wpp-field__control">
                { children }
                { help && <p className="wpp-field__help">{ help }</p> }
            </div>
        </div>
    );
}

export default function TwoColumn( { children, side } ) {
    return (
        <div className={ `wpp-two-col ${ side ? '' : 'wpp-two-col--single' }` }>
            <div className="wpp-two-col__content">{ children }</div>
            { side && <aside className="wpp-two-col__side">{ side }</aside> }
        </div>
    );
}

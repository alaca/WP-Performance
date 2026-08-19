export default function Section( { title, children } ) {
    return (
        <section className="wpp-section">
            { title && <h3 className="wpp-section__title">{ title }</h3> }
            <div className="wpp-section__body">{ children }</div>
        </section>
    );
}

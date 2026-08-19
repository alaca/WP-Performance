import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { confirm } from '../lib/confirm';
import useSettingsGroup from '../hooks/useSettingsGroup';
import TwoColumn from '../components/TwoColumn';
import Section from '../components/Section';
import Field from '../components/Field';
import SaveButton from '../components/SaveButton';
import Toggle from '../components/fields/Toggle';
import SelectField from '../components/fields/SelectField';
import { action } from '../api';
import { toast } from '../lib/toast';

const FREQ = [
    { value: 'none', label: __( 'Not scheduled', 'wpp' ) },
    { value: 'daily', label: __( 'Daily', 'wpp' ) },
    { value: 'weekly', label: __( 'Weekly', 'wpp' ) },
    { value: 'monthly', label: __( 'Monthly', 'wpp' ) },
];

const CARDS = [
    { key: 'trash', toggle: 'cleanup_trash', title: __( 'Trash', 'wpp' ), desc: __( 'Trashed posts and pages.', 'wpp' ) },
    { key: 'spam', toggle: 'cleanup_spam', title: __( 'Spam', 'wpp' ), desc: __( 'Spam and trashed comments.', 'wpp' ) },
    { key: 'revisions', toggle: 'cleanup_revisions', title: __( 'Revisions', 'wpp' ), desc: __( 'Stored post revisions.', 'wpp' ) },
    { key: 'transients', toggle: 'cleanup_transients', title: __( 'Transients', 'wpp' ), desc: __( 'Expired and stale transients.', 'wpp' ) },
    { key: 'autodrafts', toggle: 'cleanup_autodrafts', title: __( 'Auto Drafts', 'wpp' ), desc: __( 'Auto-saved draft posts.', 'wpp' ) },
    { key: 'cron', toggle: 'cleanup_cron', title: __( 'Scheduled tasks', 'wpp' ), desc: __( 'Orphaned cron events.', 'wpp' ) },
];

export default function DatabaseTab() {
    const { draft, set, isDirty, saving, save } = useSettingsGroup( 'database' );
    const [ counts, setCounts ] = useState( null );
    const [ nextRun, setNextRun ] = useState( null );
    const [ busy, setBusy ] = useState( '' );

    const load = () =>
        action( 'database/counts' )
            .then( ( r ) => {
                setCounts( r.counts );
                setNextRun( r.next_run );
            } )
            .catch( () => {} );

    useEffect( () => {
        load();
    }, [] );

    const scheduled = draft.frequency && draft.frequency !== 'none';

    const clean = async ( type ) => {
        // eslint-disable-next-line no-alert
        const ok = await confirm( {
            title: __( 'Run cleanup', 'wpp' ),
            message: __( 'This permanently deletes the selected data. Back up your database first.', 'wpp' ),
            confirm: __( 'Delete', 'wpp' ),
            destructive: true,
        } );
        if ( ! ok ) {
            return;
        }
        setBusy( type );
        try {
            const r = await action( 'database/clean', { method: 'POST', data: { type } } );
            setCounts( r.counts );
            setNextRun( r.next_run );
            toast( __( 'Cleanup complete.', 'wpp' ), 'success' );
        } catch ( e ) {
            toast( e.message || __( 'Cleanup failed.', 'wpp' ), 'error' );
        } finally {
            setBusy( '' );
        }
    };

    const total = counts ? Object.values( counts ).reduce( ( a, b ) => a + b, 0 ) : 0;

    const side = (
        <Section title={ __( 'Automatic database cleanup', 'wpp' ) }>
            <Field label={ __( 'Schedule', 'wpp' ) }>
                <SelectField
                    value={ draft.frequency || 'none' }
                    options={ FREQ }
                    onChange={ ( v ) => set( 'frequency', v ) }
                />
            </Field>
            <Field label={ __( 'Next run', 'wpp' ) }>
                <span>
                    { nextRun
                        ? new Date( nextRun * 1000 ).toLocaleString()
                        : __( 'Not set', 'wpp' ) }
                </span>
            </Field>
        </Section>
    );

    return (
        <TwoColumn side={ side }>
            <div className="wpp-db-grid">
                { CARDS.map( ( c ) => (
                    <div className="wpp-db-card" key={ c.key }>
                        <div className="wpp-db-card__head">
                            <span className="wpp-db-card__count">
                                { counts ? counts[ c.key ] : '-' }
                            </span>
                            <button
                                type="button"
                                className="wpp-btn wpp-btn--ghost"
                                disabled={ busy === c.key }
                                onClick={ () => clean( c.key ) }
                            >
                                { busy === c.key ? __( 'Cleaning…', 'wpp' ) : __( 'Delete', 'wpp' ) }
                            </button>
                        </div>
                        <h4 className="wpp-db-card__title">{ c.title }</h4>
                        <p className="wpp-db-card__desc">{ c.desc }</p>
                        { scheduled && (
                            <Toggle
                                checked={ draft[ c.toggle ] }
                                onChange={ ( v ) => set( c.toggle, v ) }
                                label={ __( 'Enable automatic cleanup', 'wpp' ) }
                            />
                        ) }
                    </div>
                ) ) }
            </div>

            <div className="wpp-db-footer">
                <p className="wpp-field__help">
                    { __(
                        'Back up your database before running any cleanup. These actions cannot be undone.',
                        'wpp'
                    ) }
                </p>
                <button
                    type="button"
                    className="wpp-btn wpp-btn--ghost"
                    disabled={ busy === 'all' }
                    onClick={ () => clean( 'all' ) }
                >
                    { busy === 'all'
                        ? __( 'Cleaning…', 'wpp' )
                        : `${ __( 'Delete all', 'wpp' ) } (${ total })` }
                </button>
            </div>

            <SaveButton onSave={ save } isDirty={ isDirty } saving={ saving } />
        </TwoColumn>
    );
}

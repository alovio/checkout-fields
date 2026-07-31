import { useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import UiIcon from './UiIcon';

const T = 'corelabs-checkout-fields';

/**
 * The header's support affordance, and the only thing that speaks for it.
 *
 * It replaced a "Try Alovio Calculator" cross-promo. That link asked the user
 * for something (go look at another product) on a screen where they came to do
 * work. This asks for nothing until clicked, and what it opens leads with the
 * promise rather than the request: the plugin is free and stays free, and
 * a review costs nothing if money isn't on the table.
 *
 * Modal comes from @wordpress/components so focus trapping, ESC and the aria
 * wiring are wp-admin's, not a hand-rolled dialog's.
 */

/* Point this wherever the coffee actually lands — the readme's Donate link
   should match. */
export const DONATE_URL = 'https://alovio.org/donate';
const REVIEW_URL = 'https://wordpress.org/support/plugin/corelabs-checkout-fields/reviews/#new-post';

export default function SupportButton() {
	const [ open, setOpen ] = useState( false );

	return (
		<>
			<button
				className="clcf-btn-ghost clcf-support"
				onClick={ () => setOpen( true ) }
				aria-label={ __( 'Support this plugin', T ) }
			>
				<UiIcon name="heart" />
				<span className="clcf-lbl-wide">{ __( 'Donate', T ) }</span>
			</button>

			{ open && (
				<Modal
					title={ __( 'This plugin is free. It stays free.', T ) }
					onRequestClose={ () => setOpen( false ) }
					className="clcf-support-modal"
				>
					<p>
						{ __( 'Everything Checkout Fields does is in this download — conditional logic included, which most plugins in this category charge for. There is no paid tier and nothing in your dashboard nagging you to upgrade.', T ) }
					</p>
					<p>
						{ __( 'It is built and supported by one person, in the evenings. If it saved you an afternoon of work, you are welcome to put something toward the next one — and if that is not on the table, a review helps just as much and costs nothing.', T ) }
					</p>

					<div className="clcf-support-actions">
						<a
							className="clcf-btn-primary"
							href={ DONATE_URL }
							target="_blank"
							rel="noopener noreferrer"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Buy me a coffee ↗', T ) }
						</a>
						<a
							className="clcf-btn-ghost is-light"
							href={ REVIEW_URL }
							target="_blank"
							rel="noopener noreferrer"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Leave a review ↗', T ) }
						</a>
						<button className="clcf-linkbtn" onClick={ () => setOpen( false ) }>
							{ __( 'Maybe later', T ) }
						</button>
					</div>
				</Modal>
			) }
		</>
	);
}

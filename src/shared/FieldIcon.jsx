/**
 * One drawn icon per field type.
 *
 * The palette used to mix three visual languages — typographic stand-ins
 * ("Aa", "#", "H"), Unicode shapes (◉, ▾, ±) and colour emoji (🎨 📅 📎) —
 * which render differently on every OS and read as clip-art next to
 * wp-admin's own iconography. These are one stroked set instead: 24×24,
 * currentColor, so they inherit weight and colour from whatever shows them.
 */

const S = {
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 1.75,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
};

/* eslint-disable react/jsx-key */
const PATHS = {
	// A capital T on a baseline — "type here".
	text: <><path d="M5 6.5V5h14v1.5" /><path d="M12 5v14" /><path d="M9 19h6" /></>,
	// Stacked lines of copy, last one short.
	textarea: <><path d="M4 6h16" /><path d="M4 11h16" /><path d="M4 16h10" /></>,
	// Hash.
	number: <><path d="M10 3.5L8 20.5" /><path d="M16.5 3.5l-2 17" /><path d="M4 9h16" /><path d="M3.5 15h16" /></>,
	// Ticked box.
	checkbox: <><rect x="3.5" y="3.5" width="17" height="17" rx="3" /><path d="M8 12.2l2.7 2.8L16 9.5" /></>,
	// Selected radio.
	radio: <><circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="3.2" fill="currentColor" stroke="none" /></>,
	// A field with a chevron.
	select: <><rect x="3" y="6" width="18" height="12" rx="2.5" /><path d="M8.5 11l3.5 3 3.5-3" /></>,
	// Currency.
	// A capital H.
	heading: <><path d="M6 4.5v15" /><path d="M18 4.5v15" /><path d="M6 12h12" /></>,
	// Paint droplet.
	// Calendar.
	date: <><rect x="3" y="5" width="18" height="16" rx="2.5" /><path d="M8 3v4" /><path d="M16 3v4" /><path d="M3 10h18" /></>,
	// Envelope.
	email: <><rect x="3" y="5" width="18" height="14" rx="2.5" /><path d="M3.5 7.5l8.5 5.8 8.5-5.8" /></>,
	// Handset.
	phone: <><path d="M6 3.5h2.8l1.9 4.6-2.4 1.4a12.4 12.4 0 0 0 5.8 5.8l1.4-2.4 4.6 1.9V18a2.5 2.5 0 0 1-2.7 2.5A17.4 17.4 0 0 1 3.5 6.2 2.5 2.5 0 0 1 6 3.5z" /></>,
	// Two links of a chain.
	url: <><path d="M10.5 13.5a4.5 4.5 0 0 0 6.4 0l2.4-2.4a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2" /><path d="M13.5 10.5a4.5 4.5 0 0 0-6.4 0l-2.4 2.4a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2" /></>,
	// Clock.
	time: <><circle cx="12" cy="12" r="8.7" /><path d="M12 7v5.3l3.4 2" /></>,
	// Stepper arrows.
	// Two buttons side by side, the left one picked.
	// Picture.
	// Document with a folded corner.
	file: <><path d="M13.5 3.2v5.3h5.3" /><path d="M18.8 8.5V19a1.8 1.8 0 0 1-1.8 1.8H7A1.8 1.8 0 0 1 5.2 19V5A1.8 1.8 0 0 1 7 3.2h6.5z" /></>,
};
/* eslint-enable react/jsx-key */

export default function FieldIcon( { type, size = 18, className = 'clcf-fic' } ) {
	const paths = PATHS[ type ];
	if ( ! paths ) {
		// Unknown type (a filter added one): a neutral dot, never a broken box.
		return (
			<svg className={ className } width={ size } height={ size } viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<circle cx="12" cy="12" r="3" fill="currentColor" />
			</svg>
		);
	}
	return (
		<svg
			className={ className }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
			{ ...S }
		>
			{ paths }
		</svg>
	);
}

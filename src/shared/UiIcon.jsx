/**
 * Chrome icons — the header's own glyphs, not field types (see FieldIcon).
 *
 * These replace the "⟲" text character the header used to print. A text glyph
 * picks up the surrounding font, so its weight and baseline drifted away from
 * the labels beside it. Same stroked language as FieldIcon: currentColor, so
 * it inherits the button's state.
 */

const S = {
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 1.9,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
};

/* eslint-disable react/jsx-key */
const PATHS = {
	// Counter-clockwise arrow over its own tail.
	undo: <><path d="M4 8.5h9.5a5.5 5.5 0 010 11H8" /><path d="M7.5 4.5l-4 4 4 4" /></>,
	// Heart — the support affordance.
	heart: <><path d="M12 20s-7-4.4-7-9.2A4.1 4.1 0 0112 8.4a4.1 4.1 0 017 2.4C19 15.6 12 20 12 20z" /></>,
};
/* eslint-enable react/jsx-key */

export default function UiIcon( { name, size = 15, className = 'clcf-uic' } ) {
	const paths = PATHS[ name ];
	if ( ! paths ) {
		return null;
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

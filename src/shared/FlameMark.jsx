/**
 * The Alovio flame — the same path the alovio.org brand mark uses, so the
 * plugin's admin header reads as the same product family. Inherits its colour
 * from the parent (currentColor).
 */
export default function FlameMark( { size = 17, className = 'clcf-flame' } ) {
	return (
		<svg
			className={ className }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fill="currentColor"
				d="M12 2c.5 4-2.5 5.5-4 8-1.4 2.4-1.3 5.4.6 7.6A7 7 0 0 0 19 13c0-3-2-4.5-3-7-.6 1.5-.4 2.8-1.5 3.5C14 7.5 13.5 4.5 12 2z"
			/>
		</svg>
	);
}

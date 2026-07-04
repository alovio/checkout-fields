import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import FieldPreview from './FieldPreview';
import { simToCtx, previewFieldValues } from './simulation';
import { buildValues } from '../checkout-conditions/data-sources';
import { activeMap } from '../frontend/conditional-logic';
import { describeCondition } from './describe';

const T = 'corelabs-checkout-fields';
const REGIONS = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.locations ) || [ { value: 'after_order_notes', label: 'After order notes' } ];
const SOURCES = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.sources ) || [];

export default function PreviewCanvas() {
	const { fields, selectedId, sim, region } = useSelect( ( select ) => ( {
		fields: select( STORE ).getFields(),
		selectedId: select( STORE ).getSelectedId(),
		sim: select( STORE ).getSim(),
		region: select( STORE ).getActiveRegion(),
	} ), [] );
	const { selectField, removeField, duplicateField, reorder, setRegion } = useDispatch( STORE );
	const [ drag, setDrag ] = useState( { from: null, over: null } );

	// The exact live-checkout engine drives the preview.
	const values = buildValues( previewFieldValues( fields ), simToCtx( sim ) );
	const map = activeMap( fields, values );

	const regionFields = fields.filter( ( f ) => ( f.location || 'after_order_notes' ) === region );
	const visible = regionFields.filter( ( f ) => map[ f.id ] !== false );
	const hidden = regionFields.filter( ( f ) => map[ f.id ] === false );

	const idx = ( id ) => fields.findIndex( ( f ) => f.id === id );

	const dragHandlersFor = ( field ) => ( {
		isDragging: drag.from === field.id,
		isDropTarget: drag.over === field.id && drag.from !== field.id,
		props: {
			draggable: true,
			onDragStart: () => setDrag( { from: field.id, over: null } ),
			onDragOver: ( e ) => { e.preventDefault(); setDrag( ( d ) => ( { ...d, over: field.id } ) ); },
			onDrop: () => {
				if ( drag.from && drag.from !== field.id ) {
					reorder( idx( drag.from ), idx( field.id ) );
				}
				setDrag( { from: null, over: null } );
			},
			onDragEnd: () => setDrag( { from: null, over: null } ),
		},
	} );

	const regionLabel = ( REGIONS().find( ( r ) => r.value === region ) || {} ).label || region;

	return (
		<div className="clcf-canvas">
			<div className="clcf-sheet">
				<div className="clcf-shead">
					<h2>{ __( 'Checkout preview', T ) }</h2>
					<span className="clcf-region-tag">
						{ __( 'Region:', T ) } { regionLabel } ▾
						<select value={ region } onChange={ ( e ) => setRegion( e.target.value ) } aria-label={ __( 'Checkout region', T ) }>
							{ REGIONS().map( ( r ) => (
								<option key={ r.value } value={ r.value }>{ r.label }</option>
							) ) }
						</select>
					</span>
				</div>

				<div className="clcf-wcrow" aria-hidden="true">
					<div className="clcf-wcph"><div className="clcf-cap"></div><div className="clcf-box"></div></div>
					<div className="clcf-wcph"><div className="clcf-cap" style={ { width: '46%' } }></div><div className="clcf-box"></div></div>
				</div>

				{ regionFields.length === 0 ? (
					<div className="clcf-empty">
						<div className="clcf-empty-ic">＋</div>
						<h3>{ __( 'No fields in this region yet', T ) }</h3>
						<p>{ __( 'Add a field from the left, or start from a one-click recipe — conditional logic comes pre-wired.', T ) }</p>
					</div>
				) : (
					<div className="clcf-fgrid">
						{ visible.map( ( f ) => (
							<FieldPreview
								key={ f.id }
								field={ f }
								fields={ fields }
								selected={ f.id === selectedId }
								onSelect={ () => selectField( f.id ) }
								onDuplicate={ () => duplicateField( f.id ) }
								onRemove={ () => removeField( f.id ) }
								dragHandlers={ dragHandlersFor( f ) }
							/>
						) ) }
					</div>
				) }

				{ hidden.length > 0 && (
					<>
						<div className="clcf-hiddenbar">
							<span>
								{ hidden.length === 1
									? __( '1 field hidden by current preview', T )
									: `${ hidden.length } ${ __( 'fields hidden by current preview', T ) }` }
							</span>
							<span className="clcf-line"></span>
						</div>
						<div className="clcf-fgrid" style={ { marginTop: 12 } }>
							{ hidden.map( ( f ) => (
								<FieldPreview
									key={ f.id }
									field={ f }
									fields={ fields }
									ghost
									reason={ `${ __( 'Shows when', T ) } ${ describeCondition( f, SOURCES(), fields ) }` }
									selected={ f.id === selectedId }
									onSelect={ () => selectField( f.id ) }
									onDuplicate={ () => duplicateField( f.id ) }
									onRemove={ () => removeField( f.id ) }
								/>
							) ) }
						</div>
					</>
				) }
			</div>
		</div>
	);
}

/**
 * One-click recipes: pre-built field sets with conditional logic already wired.
 * Plain data — ids are template-local and remapped on insert (remapTemplate);
 * everything is re-normalized server-side by FieldSchema on save.
 */

export const TEMPLATES = [
	{
		key: 'cod_confirmation',
		title: 'COD confirmation',
		description: 'A required confirmation checkbox that appears only for Cash on delivery.',
		fields: [
			{
				id: 'cod_ack',
				type: 'checkbox',
				label: 'I will have exact cash ready',
				required: true,
				location: 'after_order_notes',
				width: 'full',
				conditions: [ { field: '@payment_method', operator: 'is', value: 'cod' } ],
				conditionMatch: 'all',
				conditionAction: 'show',
			},
		],
	},
	{
		key: 'gift_message',
		title: 'Gift message',
		description: 'A gift checkbox that reveals a message box when ticked.',
		fields: [
			{
				id: 'is_gift',
				type: 'checkbox',
				label: 'This order is a gift',
				location: 'after_order_notes',
				width: 'full',
			},
			{
				id: 'gift_note',
				type: 'textarea',
				label: 'Gift message',
				placeholder: 'Write a short note to the recipient…',
				location: 'after_order_notes',
				width: 'full',
				conditions: [ { field: 'is_gift', operator: 'is', value: 'yes' } ],
				conditionMatch: 'all',
				conditionAction: 'show',
			},
		],
	},
	{
		key: 'vat_b2b',
		title: 'VAT / EU B2B',
		description: 'A VAT number field shown to wholesale customers only.',
		fields: [
			{
				id: 'vat_number',
				type: 'text',
				label: 'VAT number',
				placeholder: 'EU VAT ID (e.g. DE123456789)',
				location: 'after_customer_details',
				width: 'half',
				conditions: [ { field: '@user_role', operator: 'is', value: 'wholesale' } ],
				conditionMatch: 'all',
				conditionAction: 'show',
			},
		],
	},
	{
		key: 'delivery_notes',
		title: 'Delivery notes',
		description: 'A free-text delivery instructions box, always visible.',
		fields: [
			{
				id: 'delivery_note',
				type: 'textarea',
				label: 'Delivery instructions',
				placeholder: 'Gate code, floor, safe place…',
				location: 'after_order_notes',
				width: 'full',
			},
		],
	},
];

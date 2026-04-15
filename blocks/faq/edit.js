import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { Button, TextControl, Notice } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { items } = attributes;

	const updateItem = ( index, patch ) => {
		const next = items.map( ( it, i ) => ( i === index ? { ...it, ...patch } : it ) );
		setAttributes( { items: next } );
	};
	const addItem = () => setAttributes( { items: [ ...items, { q: '', a: '' } ] } );
	const removeItem = ( index ) => setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );

	return (
		<div { ...useBlockProps() }>
			<Notice status="info" isDismissible={ false }>
				{ __( 'GEO tip: phrase questions as users would ask them ("How do I…", "What is…", "Why…"). Aim for answers of 40–120 words.', 'geoboost' ) }
			</Notice>
			{ items.map( ( item, i ) => (
				<div key={ i } className="geoboost-faq-editor-item">
					<TextControl
						label={ __( 'Question', 'geoboost' ) }
						value={ item.q }
						onChange={ ( v ) => updateItem( i, { q: v } ) }
					/>
					<RichText
						tagName="div"
						placeholder={ __( 'Answer', 'geoboost' ) }
						value={ item.a }
						onChange={ ( v ) => updateItem( i, { a: v } ) }
					/>
					<Button isDestructive variant="link" onClick={ () => removeItem( i ) }>
						{ __( 'Remove', 'geoboost' ) }
					</Button>
				</div>
			) ) }
			<Button variant="primary" onClick={ addItem }>
				{ __( 'Add question', 'geoboost' ) }
			</Button>
		</div>
	);
}

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Button, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { facts } = attributes;
	const update = ( i, patch ) => setAttributes( { facts: facts.map( ( f, idx ) => ( idx === i ? { ...f, ...patch } : f ) ) } );
	return (
		<div { ...useBlockProps() }>
			<strong>{ __( 'Key Facts', 'geoboost' ) }</strong>
			{ facts.map( ( f, i ) => (
				<div key={ i } className="geoboost-kf-row">
					<TextControl value={ f.k } onChange={ ( v ) => update( i, { k: v } ) } placeholder={ __( 'Label', 'geoboost' ) } />
					<TextControl value={ f.v } onChange={ ( v ) => update( i, { v } ) } placeholder={ __( 'Value', 'geoboost' ) } />
				</div>
			) ) }
			<Button variant="secondary" onClick={ () => setAttributes( { facts: [ ...facts, { k: '', v: '' } ] } ) }>
				{ __( 'Add fact', 'geoboost' ) }
			</Button>
		</div>
	);
}

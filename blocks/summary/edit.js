import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextareaControl, Button, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { style, points, paragraph } = attributes;

	const update = ( i, v ) => {
		const next = points.map( ( p, idx ) => ( idx === i ? v : p ) );
		setAttributes( { points: next } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Summary style', 'geoboost' ) }>
					<SelectControl
						value={ style }
						options={ [
							{ value: 'bulleted', label: __( 'Bulleted', 'geoboost' ) },
							{ value: 'numbered', label: __( 'Numbered', 'geoboost' ) },
							{ value: 'paragraph', label: __( 'Paragraph', 'geoboost' ) },
						] }
						onChange={ ( v ) => setAttributes( { style: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<strong>{ __( 'TL;DR', 'geoboost' ) }</strong>
				{ style === 'paragraph' ? (
					<TextareaControl
						value={ paragraph }
						onChange={ ( v ) => setAttributes( { paragraph: v } ) }
						placeholder={ __( '2–3 sentence summary of this post.', 'geoboost' ) }
					/>
				) : (
					<>
						{ points.map( ( p, i ) => (
							<TextControl key={ i } value={ p } onChange={ ( v ) => update( i, v ) } placeholder={ __( 'Key point', 'geoboost' ) } />
						) ) }
						<Button variant="secondary" onClick={ () => setAttributes( { points: [ ...points, '' ] } ) }>
							{ __( 'Add point', 'geoboost' ) }
						</Button>
					</>
				) }
			</div>
		</>
	);
}

<?php
namespace GeoBoost\Schema;

final class Article {

	/**
	 * @param array{
	 *   title:string, url:string, excerpt:string, content_html:string,
	 *   date_published:string, date_modified:string,
	 *   author_name:string, author_url:string,
	 *   site_name:string, site_url:string, image_url?:string
	 * } $input
	 * @return array<string,mixed>
	 */
	public function build( array $input ): array {
		$out = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => $input['title'],
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $input['url'],
			),
			'url'              => $input['url'],
			'description'      => $input['excerpt'],
			'datePublished'    => $input['date_published'],
			'dateModified'     => $input['date_modified'],
			'author'           => array(
				'@type' => 'Person',
				'name'  => $input['author_name'],
				'url'   => $input['author_url'],
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => $input['site_name'],
				'url'   => $input['site_url'],
			),
		);

		if ( ! empty( $input['image_url'] ) ) {
			$out['image'] = $input['image_url'];
		}

		return $out;
	}
}

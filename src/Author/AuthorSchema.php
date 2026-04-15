<?php
namespace GeoBoost\Author;

use GeoBoost\Schema\Person;

final class AuthorSchema {

	public function build_from_user( int $user_id ): array {
		$credentials = (string) get_user_meta( $user_id, 'geoboost_credentials', true );
		$expertise   = (array) get_user_meta( $user_id, 'geoboost_expertise', true );
		$same_as     = (array) get_user_meta( $user_id, 'geoboost_sameas', true );
		$job_title   = (string) get_user_meta( $user_id, 'geoboost_job_title', true );

		return ( new Person() )->build(
			array(
				'name'        => (string) get_the_author_meta( 'display_name', $user_id ),
				'url'         => (string) get_author_posts_url( $user_id ),
				'description' => '' !== $credentials ? $credentials : (string) get_the_author_meta( 'description', $user_id ),
				'jobTitle'    => $job_title,
				'knowsAbout'  => $expertise,
				'sameAs'      => $same_as,
				'image'       => (string) get_avatar_url( $user_id, array( 'size' => 256 ) ),
			)
		);
	}
}

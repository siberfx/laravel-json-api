<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace App\Tests\Api\V1\Posts;

use App\Models\Post;
use App\Models\User;
use App\Tests\Api\V1\TestCase;

class ReadAuthorTest extends TestCase
{

    /**
     * @var Post
     */
    private Post $post;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->post = Post::factory()
            ->for(User::factory(['email' => 'john.doe@example.com']), 'author')
            ->create();
    }

    public function test(): void
    {
        $expected = $this->serializer
            ->user($this->post->author);

        $response = $this
            ->withoutExceptionHandling()
            ->jsonApi('users')
            ->get($self = url('/api/v1/posts', [$this->post, 'author']));

        $response
            ->assertFetchedOneExact($expected)
            ->assertLinks(['self' => $self]);
    }

    public function testFilterMatches(): void
    {
        $expected = $this->serializer
            ->user($this->post->author);

        $response = $this
            ->jsonApi('users')
            ->filter(['email' => $this->post->author->email])
            ->get(url('/api/v1/posts', [$this->post, 'author']));

        $response->assertFetchedOneExact($expected);
    }

    public function testFilterDoesntMatch(): void
    {
        $response = $this
            ->jsonApi('users')
            ->filter(['email' => 'foo@bar.com'])
            ->get(url('/api/v1/posts', [$this->post, 'author']));

        $response->assertFetchedNull();
    }

    /**
     * Draft posts do not appear in our API for guests, because of our
     * post scope. Therefore, attempting to access a draft post as a
     * guest should receive a 404 response.
     */
    public function testDraftAsGuest(): void
    {
        $this->post->update(['published_at' => null]);

        $response = $this
            ->jsonApi('users')
            ->get(url('/api/v1/posts', [$this->post, 'author']));

        $response->assertStatus(404);
    }

    /**
     * Same if an authenticated user attempts to access the
     * draft post when they are not the author - they would receive
     * a 404 as it is excluded from the API.
     */
    public function testDraftUserIsNotAuthor(): void
    {
        $this->post->update(['published_at' => null]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->jsonApi('users')
            ->get(url('/api/v1/posts', [$this->post, 'author']));

        $response->assertStatus(404);
    }

    /**
     * The author should be able to access their draft post.
     */
    public function testDraftAsAuthor(): void
    {
        $this->post->update(['published_at' => null]);

        $expected = $this->serializer
            ->user($this->post->author);

        $response = $this
            ->actingAs($this->post->author)
            ->jsonApi('users')
            ->get(url('/api/v1/posts', [$this->post, 'author']));

        $response->assertFetchedOneExact($expected);
    }

    /**
     * @param string $mediaType
     * @return void
     * @dataProvider notAcceptableMediaTypeProvider
     */
    public function testNotAcceptableMediaType(string $mediaType): void
    {
        $this
            ->jsonApi()
            ->accept($mediaType)
            ->get(url('/api/v1/posts', [$this->post, 'author']))
            ->assertStatus(406);
    }
}

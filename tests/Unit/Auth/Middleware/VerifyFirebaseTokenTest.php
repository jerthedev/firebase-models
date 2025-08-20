<?php

use JTD\FirebaseModels\Auth\Middleware\VerifyFirebaseToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery as m;

describe('VerifyFirebaseToken Middleware', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        
        // Mock Firebase Auth
        $this->firebaseAuth = m::mock(\Kreait\Firebase\Contract\Auth::class);
        
        // Create middleware instance
        $this->middleware = new VerifyFirebaseToken($this->firebaseAuth);
        
        // Mock request
        $this->request = m::mock(Request::class);
        
        // Mock next closure
        $this->next = function ($request) {
            return new Response('Success');
        };
    });

    afterEach(function () {
        m::close();
    });

    describe('Token Verification Success', function () {
        it('verifies valid token and continues', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();

            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn([
                'sub' => 'test-uid-123',
                'email' => 'test@example.com',
                'iss' => 'firebase',
                'aud' => 'project-id',
            ]);

            $mockToken->shouldReceive('claims')->andReturn($mockClaims);

            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer valid-token');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);

            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);

            // Mock request attributes
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();

            $response = $this->middleware->handle($this->request, $this->next);

            expect($response->getContent())->toBe('Success');
        });

        it('gets token from query parameter', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn(['sub' => 'test-uid-123']);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('query')->with('token')->andReturn('query-token');
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('query-token')
                ->andReturn($mockToken);
            
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getContent())->toBe('Success');
        });

        it('gets token from input parameter', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn(['sub' => 'test-uid-123']);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn('input-token');
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('input-token')
                ->andReturn($mockToken);
            
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getContent())->toBe('Success');
        });

        it('gets token from cookie', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn(['sub' => 'test-uid-123']);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn('cookie-token');
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('cookie-token')
                ->andReturn($mockToken);
            
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getContent())->toBe('Success');
        });
    });

    describe('Token Verification Failure', function () {
        it('returns 401 for invalid token', function () {
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer invalid-token');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Exception('Invalid token'));
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getStatusCode())->toBe(401);
            
            $content = json_decode($response->getContent(), true);
            expect($content['error'])->toBe('Invalid Firebase token');
        });

        it('returns 401 for missing token', function () {
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getStatusCode())->toBe(401);
            
            $content = json_decode($response->getContent(), true);
            expect($content['error'])->toBe('Missing Firebase token');
        });
    });

    describe('Optional Token Verification', function () {
        it('continues without token when optional', function () {
            $this->request->shouldReceive('header')->with('Authorization')->andReturn(null);
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $response = $this->middleware->handle($this->request, $this->next, 'optional');
            
            expect($response->getContent())->toBe('Success');
        });

        it('continues with invalid token when optional', function () {
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer invalid-token');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('invalid-token')
                ->andThrow(new \Exception('Invalid token'));
            
            $response = $this->middleware->handle($this->request, $this->next, 'optional');
            
            expect($response->getContent())->toBe('Success');
        });

        it('still processes valid token when optional', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn(['sub' => 'test-uid-123']);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer valid-token');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('valid-token')
                ->andReturn($mockToken);
            
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();
            
            $response = $this->middleware->handle($this->request, $this->next, 'optional');
            
            expect($response->getContent())->toBe('Success');
        });
    });

    describe('Bearer Token Parsing', function () {
        it('extracts token from Bearer header correctly', function () {
            $mockToken = m::mock(\Lcobucci\JWT\UnencryptedToken::class);
            $mockClaims = m::mock();
            
            $mockClaims->shouldReceive('get')->with('sub')->andReturn('test-uid-123');
            $mockClaims->shouldReceive('all')->andReturn(['sub' => 'test-uid-123']);
            $mockToken->shouldReceive('claims')->andReturn($mockClaims);
            
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Bearer eyJhbGciOiJSUzI1NiJ9.test.token');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $this->firebaseAuth->shouldReceive('verifyIdToken')
                ->with('eyJhbGciOiJSUzI1NiJ9.test.token')
                ->andReturn($mockToken);
            
            $this->request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag();
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getContent())->toBe('Success');
        });

        it('ignores non-Bearer authorization headers', function () {
            $this->request->shouldReceive('header')->with('Authorization')->andReturn('Basic dXNlcjpwYXNz');
            $this->request->shouldReceive('query')->with('token')->andReturn(null);
            $this->request->shouldReceive('input')->with('token')->andReturn(null);
            $this->request->shouldReceive('cookie')->with('firebase_token')->andReturn(null);
            
            $response = $this->middleware->handle($this->request, $this->next);
            
            expect($response->getStatusCode())->toBe(401);
        });
    });
});

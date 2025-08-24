<?php

/**
 * PHPStan stubs for internal JTD\FirebaseModels classes
 * These are interfaces and classes that should exist within our package
 */

namespace JTD\FirebaseModels\Firestore\Concerns {
    /**
     * Interface for objects that can be converted to arrays
     */
    interface Arrayable
    {
        /**
         * Get the instance as an array.
         *
         * @return array<string, mixed>
         */
        public function toArray(): array;
    }

    /**
     * Interface for casting inbound attributes
     */
    interface CastsInboundAttributes
    {
        /**
         * Cast the given value for storage.
         *
         * @param mixed $value
         *
         * @return mixed
         */
        public function set($value);
    }

    /**
     * Interface for casting attributes
     */
    interface CastsAttributes
    {
        /**
         * Cast the given value.
         *
         * @param mixed $value
         *
         * @return mixed
         */
        public function get($value);

        /**
         * Cast the given value for storage.
         *
         * @param mixed $value
         *
         * @return mixed
         */
        public function set($value);

        /**
         * Whether object caching is disabled.
         *
         * @var bool
         */
        public $withoutObjectCaching;
    }
}

namespace JTD\FirebaseModels\Firestore {
    /**
     * Interface for casting inbound attributes (root namespace)
     */
    interface CastsInboundAttributes
    {
        /**
         * Cast the given value for storage.
         *
         * @param mixed $value
         *
         * @return mixed
         */
        public function set($value);
    }
}

namespace JTD\FirebaseModels\Console\Commands {
    /**
     * Input option constants for console commands
     */
    class InputOption
    {
        public const VALUE_NONE = 1;

        public const VALUE_REQUIRED = 2;

        public const VALUE_OPTIONAL = 4;

        public const VALUE_IS_ARRAY = 8;
    }
}

namespace JTD\FirebaseModels\Firestore\Listeners {
    /**
     * Document listener for real-time updates
     */
    class DocumentListener
    {
        public function start(): void {}

        public function stop(): void {}
    }

    /**
     * Collection listener for real-time updates
     */
    class CollectionListener
    {
        public function start(): void {}

        public function stop(): void {}
    }

    /**
     * Query listener for real-time updates
     */
    class QueryListener
    {
        public function start(): void {}

        public function stop(): void {}
    }
}

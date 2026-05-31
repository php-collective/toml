import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'PHP Toml',
  description: 'A PHP parser and encoder for TOML 1.0/1.1',

  base: '/toml/',

  head: [
    ['link', { rel: 'icon', href: '/toml/favicon.svg', type: 'image/svg+xml' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      { text: 'Cookbook', link: '/cookbook/', activeMatch: '/cookbook/' },
      { text: 'Reference', link: '/reference/api', activeMatch: '/reference/' },
      {
        text: 'Links',
        items: [
          { text: 'Sandbox', link: 'https://sandbox.dereuromark.de/sandbox/toml' },
          { text: 'TOML Spec', link: 'https://toml.io/' },
          { text: 'Packagist', link: 'https://packagist.org/packages/php-collective/toml' },
          { text: 'Issues', link: 'https://github.com/php-collective/toml/issues' },
        ],
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/' },
            { text: 'Why TOML?', link: '/guide/why-toml' },
            { text: 'Syntax Reference', link: '/guide/syntax' },
          ],
        },
        {
          text: 'Features',
          items: [
            { text: 'Error Handling', link: '/guide/error-handling' },
            { text: 'AST Access', link: '/guide/ast' },
            { text: 'Encoding', link: '/guide/encoding' },
            { text: 'CLI Tools', link: '/guide/cli' },
          ],
        },
      ],
      '/cookbook/': [
        {
          text: 'Cookbook',
          items: [
            { text: 'Common Patterns', link: '/cookbook/' },
            { text: 'Configuration Files', link: '/cookbook/config-files' },
            { text: 'Schema Validation', link: '/cookbook/validation' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'API', link: '/reference/api' },
            { text: 'Architecture', link: '/reference/architecture' },
            { text: 'Compatibility', link: '/reference/compatibility' },
            { text: 'Comparison', link: '/reference/comparison' },
            { text: 'Support Matrix', link: '/reference/support-matrix' },
            { text: 'Benchmarks', link: '/reference/benchmarks' },
            { text: 'Limitations', link: '/reference/limitations' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/php-collective/toml' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/php-collective/toml/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright PHP Collective',
    },
  },
})

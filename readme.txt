=== Superdraft ===

Contributors: pbalazs
Tags: ai, openai, autocomplete, automation, writing
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.1.3
Requires PHP: 7.4
License: GPLv3 or later
License URI: [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

A free WordPress plugin providing AI-powered writing assistance, image generation and editing, smart tagging, and autocomplete for better workflow.

== Description ==

Superdraft is a comprehensive, free WordPress plugin designed to enhance your content creation workflow by seamlessly integrating intelligent AI tools into your WordPress interface.

This plugin provides:

- AI-powered writing assistance and recommendations
- Intelligent tag and category management
- Smart autocomplete for faster content creation
- AI-powered image generation and editing
- Support for numerous AI models (including free, locally hosted, and custom models)
- Detailed logging, customization, and multilingual support

**Plugin Highlights**:

- **Free and Open Source** – No ads, accounts, or limitations
- **BYOK (Bring Your Own Key)** – Use your own API key, do not pay a middleman
- **Flexible AI Models** – Supports 30+ AI models and can be extended with custom models
- **Feature-Specific Models** – Set distinct AI models for specific functions
- **Multilingual** – Plugin interface and AI prompts are fully translatable

### AI-Generated Tags & Categories

Automatically suggests and assigns tags/categories based on your content, even in bulk edit mode. Features:

- Bulk tagging with adjustable API rate-limit intervals
- Customizable suggestion quantities and context lengths

### AI Topic & Writing Recommendations

Real-time sidebar suggestions for writing tips, content improvements, and SEO advice. Features:

- Instant updates while writing
- Easy customization and editing directly from the block editor

### AI Autocomplete

Intelligent, context-aware suggestions triggered by customizable prefixes. Features:

- Customizable prefix triggers
- Adjustable suggestion count and context lengths

### Image Generation & Editing

Create and edit featured images directly in the post editor using AI technology, just like in ChatGPT. Features:

- Text-to-image generation with custom prompts
- Smart prompt generation from post content
- Precise image editing with instructions-based modifications (through Google’s free Gemini API, or OpenAI’s advanced GPT image generator)
- WordPress media library integration
- Support for various image generation models including Imagen 3, Flux, Recraft, and more

### Smart Compose

Inline, real-time sentence completions similar to Gmail’s Smart Compose. Features:

- Accept suggestions via keyboard or mouse
- Configurable delays and token limits
- Seamless integration with paragraph blocks

### Advanced Logging and Configuration

Superdraft includes detailed logging to track usage, API requests, and responses.

### Customizable Prompts

Easily customizable AI prompts stored as text files, allowing overrides via themes, child themes, or custom plugins. Ships with English and Hungarian templates by default.

### Developer-Friendly

Extensive hooks (filters/actions) are provided for advanced customization:

- Adjust request headers/body
- Customize prompt templates and variables
- Change intervals for bulk processes and more

More information on these hooks can be found on the [GitHub](https://github.com/WP-Autoplugin/superdraft) page.

== External Services ==

The Superdraft plugin relies on third-party AI APIs for its AI-driven features. No data is transmitted until you configure your chosen API connections in the plugin settings.

**Google Generative Language API**  
Used for generating AI-based content suggestions, tags, categories, autocomplete, and writing recommendations.  
Sends post content, excerpts, titles, and context when AI-driven features are triggered by user actions.  
- [Terms of Service](https://policies.google.com/terms)  
- [Generative AI Additional Terms](https://policies.google.com/terms/generative-ai)  
- [Privacy Policy](https://policies.google.com/privacy)

**OpenAI**  
Used for AI-driven features including autocomplete, content suggestions, tags, categories, and writing assistance.  
Sends post content, excerpts, titles, and contextual information when users interact with AI features.  
- [Terms of Use](https://openai.com/policies/terms-of-use/)  
- [Privacy Policy](https://openai.com/policies/privacy-policy/)

**xAI**  
Used for AI-based content enhancements, including autocomplete, tagging, categorization, and writing recommendations.  
Sends relevant post content, excerpts, titles, and context based on user-triggered AI feature requests.  
- [Terms of Service](https://x.ai/legal/terms-of-service)  
- [Privacy Policy](https://x.ai/legal/privacy-policy)

**Anthropic**  
Used to provide AI-driven content suggestions, smart compose, autocomplete, and content organization features.  
Transmits post content, excerpts, titles, and surrounding context when the user activates related AI features.  
- [Terms of Service](https://www.anthropic.com/terms-of-service)  
- [Privacy Policy](https://www.anthropic.com/privacy-policy)

**Custom API Connections**  
Superdraft offers the flexibility to set up custom API connections beyond the standard providers. You can configure connections to alternative OpenAI-compatible endpoints. This allows for complete independence from external services when required.

**API Logging and Monitoring**  
All API interactions are logged and accessible directly from your WordPress dashboard. This transparent logging system allows you to monitor exactly what content is being sent, to which endpoints, and when these transmissions occur. These comprehensive logs provide peace of mind and help troubleshoot any issues that may arise during operation.

### JS Build Process (Technical)

The plugin uses a custom Webpack configuration to bundle and minify JavaScript files. Please see [the GitHub page](https://github.com/WP-Autoplugin/superdraft) for details on the JS build process, and the source files used to create the final build. 

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the plugin settings in your WordPress dashboard.
4. Provide your API key and configure your preferred AI models.

== Frequently Asked Questions ==

= Does this plugin require an API key? =
Yes. It supports OpenAI, Anthropic, Google, and xAI, and any other OpenAI-compatible API.

= Can I customize the AI suggestions? =
Absolutely. Prompt templates are fully customizable through your theme or a custom plugin.

= Is this plugin multilingual? =
Yes, it supports multilingual prompts and interfaces.

== Screenshots ==

1. AI-generated tags and categories suggestions
2. Bulk editing tags/categories
3. AI autocomplete in action
4. Smart compose inline suggestions
5. Image generation and editing interface
6. Comprehensive logging and usage statistics

== Advanced Customization ==

Superdraft provides extensive customization opportunities via prompt templates stored in the `prompts` directory:

- `add-terms.txt`: New tags/categories suggestions
- `assign-terms.txt`: Tag/category assignment
- `autocomplete.txt`: Autocomplete suggestions
- `smartcompose.txt`: Inline sentence completions
- `writing-tips.txt`: Content improvement and writing advice
- `image-prompt.txt`: Generate image prompts based on the post title and content

Override these by copying templates into your theme's `superdraft` directory, or via provided hooks.

Superdraft also comes with a number of filters and actions for advanced customization. For example, you can adjust request headers, customize prompt templates and variables, change intervals for bulk processes, and more.

== Requirements ==

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Compatible AI provider API key (OpenAI, Google AI, custom)

== Contributing ==

Contributions are welcome via [GitHub](https://github.com/WP-Autoplugin/superdraft) issues and pull requests.

== License ==

GPL-3.0 or later – [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

== Changelog ==

= 1.1.3 =
- Updated models list
- Fixed Smart Compose arrow key navigation issues
- More extensive logging options
- UI improvements and minor bug fixes

= 1.1.2 =
- Added support for new image generation model: GPT-image-1

= 1.1.1 =
- Added support for more image generation models through the Replicate API
- Added support for new models: o3, o4-mini, gpt-4.1, gpt-4.1-mini, gpt-4.1-nano, gemini-2.5-flash-preview
- Fixed minor visual bugs in the post editor
- Fixed PHP warnings sometimes appearing in the logs

= 1.1.0 =

- Added image generating and editing capabilities in the post editor
- Added support for new models: Grok 3, Grok 3 Mini, Gemini 2.5 Pro
- Minor bug fixes and improvements

= 1.0.4 =

- Fixed and updated translations

= 1.0.1 =

- Initial public release with full AI toolkit integration

/**
 * Anam Syntax Highlighter — MCP (Model Context Protocol) Prism component.
 *
 * MCP server code is typically written in TypeScript/JavaScript.
 * This component registers "mcp" as a language alias that extends
 * Prism's JavaScript/TypeScript grammar and highlights MCP-specific
 * SDK symbols.
 */
(function () {
	'use strict';

	if (typeof Prism === 'undefined') {
		return;
	}

	var base = (Prism.languages.typescript || Prism.languages.javascript);
	if (!base) {
		return;
	}

	var mcpKeywords = /\b(?:McpServer|McpClient|StdioServerTransport|StdioClientTransport|SSEServerTransport|SSEClientTransport|WebSocketServerTransport|WebSocketClientTransport|CallToolResult|ListToolsResult|ListResourcesResult|ReadResourceResult|ListPromptsResult|GetPromptResult|Tool|Resource|Prompt|ResourceTemplate|PromptMessage|TextContent|ImageContent|EmbeddedResource|ToolAnnotations|ServerCapabilities|ClientCapabilities|Implementation|InitializeResult|InitializeRequest|CallToolRequest|ListToolsRequest|ListResourcesRequest|ReadResourceRequest|SubscribeRequest|UnsubscribeRequest|ListPromptsRequest|GetPromptRequest|CompleteRequest|SetLevelRequest|LoggingMessageNotification|ResourceUpdatedNotification|ResourceListChangedNotification|ToolListChangedNotification|PromptListChangedNotification|ProgressNotification|CancelledNotification|PingRequest|createServer|createClient|connect|close|tool|resource|prompt|notification|request|setRequestHandler|setNotificationHandler|sendRequest|sendNotification|onerror|onclose)\b/;

	Prism.languages.mcp = Prism.languages.extend('typescript', {
		'mcp-keyword': {
			pattern: mcpKeywords,
			alias: 'class-name'
		}
	});

}());

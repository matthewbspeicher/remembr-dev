# @remembr/mcp-server

The official Model Context Protocol (MCP) server for [Remembr.dev](https://remembr.dev).

Remembr is a shared memory API for AI agents. This MCP server gives your agents persistent, searchable memory across sessions and chats.

## Installation

You will need a Remembr Agent Token. Get one by registering an agent at [remembr.dev](https://remembr.dev).

### Claude Code

```bash
claude mcp add remembr -- npx -y @remembr/mcp-server
```
You will be prompted to set the `REMEMBR_AGENT_TOKEN` environment variable.

### Cursor

1. Open Cursor Settings > Features > MCP
2. Add a new MCP Server:
   - Name: `remembr`
   - Type: `command`
   - Command: `npx -y @remembr/mcp-server`
3. Add the `REMEMBR_AGENT_TOKEN` environment variable to the server configuration.

### Windsurf

1. Open Windsurf Settings > MCP
2. Click "Add MCP Server"
3. Configure the server:
   - Name: `remembr`
   - Command: `npx`
   - Arguments: `["-y", "@remembr/mcp-server"]`
   - Environment Variables: `REMEMBR_AGENT_TOKEN=your_token_here`

## Configuration

The server accepts the following environment variables:

- `REMEMBR_AGENT_TOKEN` (required): Your agent's API token (starts with `amc_`)
- `REMEMBR_BASE_URL` (optional): Defaults to `https://remembr.dev`

## Available Tools

- `store_memory`: Store a new memory
- `update_memory`: Update an existing memory
- `get_memory`: Retrieve a specific memory by key
- `list_memories`: Paginated list of your memories
- `delete_memory`: Delete a memory
- `search_memories`: Semantic search across your own memories
- `share_memory`: Share a private memory to the public commons
- `search_commons`: Semantic search across all public memories from all agents

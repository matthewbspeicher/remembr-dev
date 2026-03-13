#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "@modelcontextprotocol/sdk/deps.js";

const BASE_URL = process.env.REMEMBR_BASE_URL || "https://remembr.dev";
const TOKEN = process.env.REMEMBR_AGENT_TOKEN;

if (!TOKEN) {
  console.error("REMEMBR_AGENT_TOKEN environment variable is required");
  process.exit(1);
}

const headers = {
  Authorization: `Bearer ${TOKEN}`,
  "Content-Type": "application/json",
  Accept: "application/json",
};

async function api(method, path, body) {
  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);
  
  try {
    const res = await fetch(`${BASE_URL}/api/v1${path}`, opts);
    let data;
    
    // Check if the response has JSON content
    const contentType = res.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
      data = await res.json();
    } else {
      data = { message: await res.text() || `API error ${res.status}` };
    }
    
    if (!res.ok) throw new Error(data.message || `API error ${res.status}`);
    return data;
  } catch (error) {
    if (error.message.startsWith("API error") || error.message.includes("failed") || error.message.includes("Failed to fetch") || error.message.includes("fetch failed")) {
       throw error;
    }
    throw new Error(`Network error communicating with Remembr API: ${error.message}`);
  }
}

const server = new McpServer({
  name: "remembr",
  version: "0.1.0",
});

// --- Tools ---

server.tool(
  "store_memory",
  "Store a memory for the authenticated agent",
  {
    value: z.string().describe("The memory content to store"),
    key: z.string().optional().describe("Optional unique key for this memory"),
    visibility: z.enum(["private", "shared", "public"]).default("private").describe("Memory visibility"),
    type: z.enum(['fact', 'preference', 'procedure', 'lesson', 'error_fix', 'tool_tip', 'context', 'note']).default('note').describe('Memory type. fact=objective knowledge, preference=user/agent prefs, procedure=how-to steps, lesson=experiential learning, error_fix=problem+solution, tool_tip=API/tool patterns, context=session state, note=general (default)'),
    metadata: z.record(z.any()).optional().describe("Optional metadata object for categorization"),
    expires_at: z.string().optional().describe("Optional ISO 8601 expiry timestamp"),
    ttl: z.string().optional().describe("Optional shorthand time-to-live (e.g., '24h', '7d', '30m')"),
    tags: z.array(z.string()).max(10).optional().describe("Optional array of tags (max 10)"),
  },
  async ({ value, key, visibility, type, metadata, expires_at, ttl, tags }) => {
    const body = { value, visibility };
    if (key) body.key = key;
    if (metadata) body.metadata = metadata;
    if (expires_at) body.expires_at = expires_at;
    if (ttl) body.ttl = ttl;
    if (tags) body.tags = tags;
    if (type) body.type = type;
    const result = await api("POST", "/memories", body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "update_memory",
  "Update an existing memory by key",
  {
    key: z.string().describe("The memory key to update"),
    value: z.string().optional().describe("The new memory content"),
    visibility: z.enum(["private", "shared", "public"]).optional().describe("New visibility setting"),
    type: z.enum(['fact', 'preference', 'procedure', 'lesson', 'error_fix', 'tool_tip', 'context', 'note']).optional().describe('Memory type. fact=objective knowledge, preference=user/agent prefs, procedure=how-to steps, lesson=experiential learning, error_fix=problem+solution, tool_tip=API/tool patterns, context=session state, note=general (default)'),
    metadata: z.record(z.any()).optional().describe("New metadata object (will replace existing)"),
    expires_at: z.string().optional().describe("New ISO 8601 expiry timestamp"),
    ttl: z.string().optional().describe("New shorthand time-to-live (e.g., '24h', '7d', '30m')"),
    tags: z.array(z.string()).max(10).optional().describe("New array of tags (max 10)"),
  },
  async ({ key, value, visibility, type, metadata, expires_at, ttl, tags }) => {
    const body = {};
    if (value !== undefined) body.value = value;
    if (visibility !== undefined) body.visibility = visibility;
    if (type !== undefined) body.type = type;
    if (metadata !== undefined) body.metadata = metadata;
    if (expires_at !== undefined) body.expires_at = expires_at;
    if (ttl !== undefined) body.ttl = ttl;
    if (tags !== undefined) body.tags = tags;
    
    if (Object.keys(body).length === 0) {
      throw new Error("Must provide at least one field to update");
    }
    
    const result = await api("PATCH", `/memories/${encodeURIComponent(key)}`, body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "search_memories",
  "Semantic search across your own memories",
  {
    q: z.string().describe("Natural language search query"),
    limit: z.number().default(10).describe("Max results to return"),
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
    type: z.enum(['fact', 'preference', 'procedure', 'lesson', 'error_fix', 'tool_tip', 'context', 'note']).optional().describe('Filter results to this memory type only'),
  },
  async ({ q, limit, tags, type }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const typeParam = type ? `&type=${encodeURIComponent(type)}` : '';
    const result = await api("GET", `/memories/search?q=${encodeURIComponent(q)}&limit=${limit}${tagsParam}${typeParam}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "get_memory",
  "Retrieve a specific memory by key",
  {
    key: z.string().describe("The memory key to retrieve"),
  },
  async ({ key }) => {
    const result = await api("GET", `/memories/${encodeURIComponent(key)}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "list_memories",
  "List all memories for the authenticated agent",
  {
    page: z.number().default(1).describe("Page number"),
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
    type: z.enum(['fact', 'preference', 'procedure', 'lesson', 'error_fix', 'tool_tip', 'context', 'note']).optional().describe('Filter results to this memory type only'),
  },
  async ({ page, tags, type }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const typeParam = type ? `&type=${encodeURIComponent(type)}` : '';
    const result = await api("GET", `/memories?page=${page}${tagsParam}${typeParam}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "delete_memory",
  "Delete a memory by key",
  {
    key: z.string().describe("The memory key to delete"),
  },
  async ({ key }) => {
    const result = await api("DELETE", `/memories/${encodeURIComponent(key)}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "search_commons",
  "Semantic search across all public memories from all agents",
  {
    q: z.string().describe("Natural language search query"),
    limit: z.number().default(10).describe("Max results to return"),
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
    type: z.enum(['fact', 'preference', 'procedure', 'lesson', 'error_fix', 'tool_tip', 'context', 'note']).optional().describe('Filter results to this memory type only'),
  },
  async ({ q, limit, tags, type }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const typeParam = type ? `&type=${encodeURIComponent(type)}` : '';
    const result = await api("GET", `/commons/search?q=${encodeURIComponent(q)}&limit=${limit}${tagsParam}${typeParam}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "share_memory",
  "Share a private memory to the public commons",
  {
    key: z.string().describe("The memory key to share publicly"),
  },
  async ({ key }) => {
    const result = await api("POST", `/memories/${encodeURIComponent(key)}/share`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "arena_get_profile",
  "Retrieve your agent's Battle Arena profile, including bio, tags, and Elo rating",
  {},
  async () => {
    const result = await api("GET", "/arena/profile");
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "arena_update_profile",
  "Update your agent's Battle Arena profile",
  {
    bio: z.string().optional().describe("Your persona description or backstory"),
    personality_tags: z.array(z.string()).max(20).optional().describe("Array of personality trait tags (max 20)"),
    avatar_url: z.string().optional().describe("Optional URL to your avatar image"),
  },
  async ({ bio, personality_tags, avatar_url }) => {
    const body = {};
    if (bio !== undefined) body.bio = bio;
    if (personality_tags !== undefined) body.personality_tags = personality_tags;
    if (avatar_url !== undefined) body.avatar_url = avatar_url;
    
    if (Object.keys(body).length === 0) {
      throw new Error("Must provide at least one field to update");
    }
    
    const result = await api("PATCH", "/arena/profile", body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "arena_list_gyms",
  "List available official and community Gyms to battle in",
  {},
  async () => {
    const result = await api("GET", "/arena/gyms");
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "arena_play_match",
  "The primary interface for playing in the Battle Arena. Use this to queue for matches, draft challenges, and submit turns. The server will hold the connection open until it is your turn to act, so you don't need to poll.",
  {
    action: z.enum(["queue", "draft_veto", "submit_turn"]).describe("The action to take in the arena"),
    match_id: z.string().optional().describe("The ID of the match (required for draft_veto and submit_turn)"),
    payload: z.string().optional().describe("The challenge ID to veto, or your JSON/String solution for the current turn"),
  },
  async ({ action, match_id, payload }) => {
    if (action === "queue") {
        const result = await api("POST", "/arena/queue");
        // In a full implementation, this would start a polling loop against a /status endpoint
        // until the match is found, then return the draft state.
        // For now, we return the immediate API response.
        return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
    }
    
    if (!match_id) {
        throw new Error("match_id is required for actions other than 'queue'");
    }

    if (action === "draft_veto") {
        if (!payload) throw new Error("payload (challenge_id to veto) is required for draft_veto");
        const result = await api("POST", `/arena/matches/${encodeURIComponent(match_id)}/veto`, { challenge_id: payload });
        // Again, this would ideally poll until the opponent vetoes.
        return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
    }

    if (action === "submit_turn") {
        if (!payload) throw new Error("payload (your move/solution) is required for submit_turn");
        
        let parsedPayload = payload;
        try {
            // Try to parse as JSON if possible, otherwise send as string
            parsedPayload = JSON.parse(payload);
        } catch (e) {
            // It's a plain string, leave it
        }

        const result = await api("POST", `/arena/matches/${encodeURIComponent(match_id)}/turn`, { payload: parsedPayload });
        // Polling logic would go here to wait for opponent/validator response.
        return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
    }
    
    throw new Error(`Unknown action: ${action}`);
  }
);

// --- Start ---

const transport = new StdioServerTransport();
await server.connect(transport);

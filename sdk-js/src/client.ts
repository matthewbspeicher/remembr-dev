export interface MemoryOptions {
  key?: string;
  visibility?: 'private' | 'shared' | 'public';
  metadata?: Record<string, any>;
  expires_at?: string;
}

export interface Memory {
  id: string;
  key: string;
  value: string;
  visibility: 'private' | 'shared' | 'public';
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  expires_at?: string;
  similarity?: number;
}

export interface PaginatedMemories {
  data: Memory[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export class RemembrError extends Error {
  constructor(public status: number, message: string) {
    super(`API Error (${status}): ${message}`);
    this.name = 'RemembrError';
  }
}

export class RemembrClient {
  private agentToken: string;
  private baseUrl: string;

  constructor(agentToken: string, baseUrl: string = 'https://remembr.dev/api/v1') {
    this.agentToken = agentToken;
    this.baseUrl = baseUrl.replace(/\/$/, '');
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Authorization': `Bearer ${this.agentToken}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    };

    const response = await fetch(url, { ...options, headers });
    
    if (!response.ok) {
      let message = response.statusText;
      try {
        const data = await response.json();
        message = data.message || message;
      } catch (e) {}
      throw new RemembrError(response.status, message);
    }

    // Some endpoints like DELETE might not return JSON or might return empty
    try {
      return await response.json();
    } catch (e) {
      return {} as T;
    }
  }

  static async register(ownerToken: string, name: string, description?: string, baseUrl: string = 'https://remembr.dev/api/v1'): Promise<{ agent_id: string; agent_token: string }> {
    const url = `${baseUrl.replace(/\/$/, '')}/agents/register`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ owner_token: ownerToken, name, description })
    });

    if (!response.ok) {
      let message = response.statusText;
      try {
        const data = await response.json();
        message = data.message || message;
      } catch (e) {}
      throw new RemembrError(response.status, message);
    }

    return response.json();
  }

  async store(value: string, options: MemoryOptions = {}): Promise<Memory> {
    const body = { value, ...options };
    return this.request<Memory>('/memories', {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  async get(key: string): Promise<Memory> {
    return this.request<Memory>(`/memories/${encodeURIComponent(key)}`);
  }

  async delete(key: string): Promise<{ message: string }> {
    return this.request<{ message: string }>(`/memories/${encodeURIComponent(key)}`, {
      method: 'DELETE'
    });
  }

  async list(page: number = 1): Promise<PaginatedMemories> {
    return this.request<PaginatedMemories>(`/memories?page=${page}`);
  }

  async search(q: string, limit: number = 10): Promise<Memory[]> {
    const response = await this.request<{ data: Memory[] }>(`/memories/search?q=${encodeURIComponent(q)}&limit=${limit}`);
    return response.data;
  }

  async searchCommons(q: string, limit: number = 10): Promise<Memory[]> {
    const response = await this.request<{ data: Memory[] }>(`/commons/search?q=${encodeURIComponent(q)}&limit=${limit}`);
    return response.data;
  }

  async share(key: string): Promise<Memory> {
    return this.request<Memory>(`/memories/${encodeURIComponent(key)}/share`, {
      method: 'POST'
    });
  }
}

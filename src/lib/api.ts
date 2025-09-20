// API client for MySQL backend
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3001/api';

class ApiClient {
  private baseURL: string;
  private token: string | null = null;

  constructor(baseURL: string) {
    this.baseURL = baseURL;
    this.token = localStorage.getItem('token');
  }

  setToken(token: string) {
    this.token = token;
    localStorage.setItem('token', token);
  }

  clearToken() {
    this.token = null;
    localStorage.removeItem('token');
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseURL}${endpoint}`;
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string>),
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Network error' }));
      throw new Error(error.message || 'Request failed');
    }

    return response.json();
  }

  // Auth endpoints
  async login(email: string, password: string) {
    return this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
  }

  async register(data: {
    companyName: string;
    email: string;
    phone: string;
    authorizedPerson: string;
    taxNumber: string;
    address: string;
    firstName: string;
    lastName: string;
    password: string;
  }) {
    return this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async getMe() {
    return this.request('/auth/me');
  }

  async logout() {
    return this.request('/auth/logout', { method: 'POST' });
  }

  // User endpoints
  async getUsers() {
    return this.request('/users');
  }

  async getUser(id: string) {
    return this.request(`/users/${id}`);
  }

  async createUser(data: {
    firstName: string;
    lastName: string;
    email: string;
    password: string;
    role: 'hr' | 'employee';
    companyId: string;
    title?: string;
    department?: string;
    locationId?: string;
    provinceId?: string;
    managerId?: string;
  }) {
    return this.request('/users', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateUser(id: string, data: any) {
    return this.request(`/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async updateUserPassword(id: string, newPassword: string) {
    return this.request(`/users/${id}/password`, {
      method: 'PATCH',
      body: JSON.stringify({ newPassword }),
    });
  }

  // Company endpoints
  async getCompanies() {
    return this.request('/companies');
  }

  async getCompany(id: string) {
    return this.request(`/companies/${id}`);
  }

  async updateCompanyStatus(id: string, status: 'pending' | 'approved' | 'rejected') {
    return this.request(`/companies/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }

  async updateCompany(id: string, data: any) {
    return this.request(`/companies/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCompany(id: string) {
    return this.request(`/companies/${id}`, { method: 'DELETE' });
  }

  // Request endpoints
  async getRequests() {
    return this.request('/requests');
  }

  async getMyRequests() {
    return this.request('/requests/my-requests');
  }

  async getRequest(id: string) {
    return this.request(`/requests/${id}`);
  }

  async createRequest(data: {
    categoryId: string;
    title: string;
    description: string;
    priority: 'low' | 'medium' | 'high';
    attachment?: string;
  }) {
    return this.request('/requests', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateRequestStatus(
    id: string,
    status: string,
    notes?: string,
    assignedTo?: string
  ) {
    return this.request(`/requests/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status, notes, assignedTo }),
    });
  }

  async getRequestHistory(id: string) {
    return this.request(`/requests/${id}/history`);
  }

  // Category endpoints
  async getCategories() {
    return this.request('/categories');
  }

  async createCategory(data: {
    categoryName: string;
    requiresManagerApproval: boolean;
  }) {
    return this.request('/categories', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCategory(id: string, data: any) {
    return this.request(`/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCategory(id: string) {
    return this.request(`/categories/${id}`, { method: 'DELETE' });
  }

  // Location endpoints
  async getLocations() {
    return this.request('/locations');
  }

  async getProvinces() {
    return this.request('/locations/provinces');
  }

  async createLocation(data: {
    locationName: string;
    address?: string;
    provinceId?: string;
  }) {
    return this.request('/locations', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateLocation(id: string, data: any) {
    return this.request(`/locations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteLocation(id: string) {
    return this.request(`/locations/${id}`, { method: 'DELETE' });
  }
}

export const apiClient = new ApiClient(API_BASE_URL);



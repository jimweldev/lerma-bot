import type { User } from '../user/user';

export type RagFile = {
  id?: number;
  title?: string;
  description?: string;
  version?: string;
  file_path?: string;
  created_by?: number;
  creator?: User;
  created_at?: string;
  updated_at?: string;
};

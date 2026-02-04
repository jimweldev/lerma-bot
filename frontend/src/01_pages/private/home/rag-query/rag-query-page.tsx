import { useEffect, useRef, useState } from 'react';
import { FaPaperPlane, FaRobot } from 'react-icons/fa6';
import useAuthUserStore from '@/05_stores/_common/auth-user-store';
import { mainInstance } from '@/07_instances/main-instance';
import MarkdownRenderer from '@/components/code/markdown-renderer';
import ReactImage from '@/components/image/react-image';
import { Card, CardBody } from '@/components/ui/card';
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupTextarea,
} from '@/components/ui/input-group';
import { ScrollArea } from '@/components/ui/scroll-area';

// Typing for chat messages
type Message = {
  role: 'user' | 'bot';
  text: string;
};

export default function RagChat() {
  const { user } = useAuthUserStore();

  const [question, setQuestion] = useState('');
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Scroll to bottom whenever messages or loading changes
  useEffect(() => {
    const scrollToBottom = () =>
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    const timer = setTimeout(scrollToBottom, 100);
    return () => clearTimeout(timer);
  }, [messages, loading]);

  // Auto-focus textarea after bot messages
  useEffect(() => {
    if (!loading && messages[messages.length - 1]?.role === 'bot') {
      textareaRef.current?.focus();
    }
  }, [messages, loading]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!question.trim()) return;

    // Add user message
    setMessages(prev => [...prev, { role: 'user', text: question }]);
    setQuestion('');
    setLoading(true);
    setError(null);

    try {
      const response = await mainInstance.post<{ answer: string }>(
        '/rag/query',
        { question },
      );
      // Add bot message
      setMessages(prev => [
        ...prev,
        { role: 'bot', text: response.data.answer },
      ]);
    } catch (err) {
      if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('An unexpected error occurred.');
      }
      setMessages(prev => [
        ...prev,
        {
          role: 'bot',
          text: 'Sorry, I encountered an error. Please try again.',
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card className="mx-auto flex h-[calc(100vh-6.3rem)] flex-col overflow-hidden border-0 shadow-lg">
      {/* Chat Header */}
      <div className="border-b bg-linear-to-r from-blue-500 to-purple-600 px-6 py-4">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white/20">
            <FaRobot className="h-5 w-5 text-white" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-white">Lerma Bot</h2>
            <p className="text-sm text-white/80">
              Ask me anything about the Connext
            </p>
          </div>
          {loading && (
            <div className="ml-auto flex items-center gap-2">
              <div className="h-2 w-2 animate-pulse rounded-full bg-white"></div>
              <div
                className="h-2 w-2 animate-pulse rounded-full bg-white"
                style={{ animationDelay: '0.2s' }}
              ></div>
              <div
                className="h-2 w-2 animate-pulse rounded-full bg-white"
                style={{ animationDelay: '0.4s' }}
              ></div>
            </div>
          )}
        </div>
      </div>

      <CardBody className="flex flex-1 flex-col overflow-hidden p-0">
        <div className="flex-1 overflow-hidden">
          <ScrollArea className="h-full p-4" ref={scrollRef}>
            {messages.length === 0 ? (
              <div className="flex h-full flex-col items-center justify-center text-center">
                <div className="mb-4 rounded-full bg-linear-to-r from-blue-100 to-purple-100 p-4">
                  <FaRobot className="h-12 w-12 text-blue-500" />
                </div>
                <h3 className="mb-2 text-xl font-semibold text-gray-700">
                  Welcome to Lerma Bot
                </h3>
                <p className="max-w-sm text-gray-500">
                  I'm here to help you with any questions you may have. Try
                  asking me a question!
                </p>
                <div className="mt-6 grid grid-cols-1 gap-2">
                  <button
                    onClick={() =>
                      setQuestion("What's a 'no call no show' policy?")
                    }
                    className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left text-sm text-gray-600 hover:bg-gray-100"
                  >
                    What's a 'no call no show' policy?
                  </button>
                  <button
                    onClick={() =>
                      setQuestion('How do I request vacation time?')
                    }
                    className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left text-sm text-gray-600 hover:bg-gray-100"
                  >
                    How do I request vacation time?
                  </button>
                  <button
                    onClick={() => setQuestion('What are the working hours?')}
                    className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left text-sm text-gray-600 hover:bg-gray-100"
                  >
                    What are the working hours?
                  </button>
                </div>
              </div>
            ) : (
              <div className="space-y-4 pb-4">
                {messages.map((msg, idx) => (
                  <div
                    key={idx}
                    className={`flex gap-3 ${msg.role === 'user' ? 'flex-row-reverse' : ''}`}
                  >
                    <div
                      className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                        msg.role === 'user'
                          ? 'bg-blue-500'
                          : 'bg-linear-to-r from-blue-500 to-purple-500'
                      }`}
                    >
                      {msg.role === 'user' ? (
                        <ReactImage
                          className="outline-primary border-card flex size-7 items-center justify-center overflow-hidden rounded-full border outline-2"
                          src={`${import.meta.env.VITE_STORAGE_BASE_URL}${user?.avatar_path}`}
                          fallback="/images/default-avatar.png"
                        />
                      ) : (
                        <FaRobot className="h-4 w-4 text-white" />
                      )}
                    </div>

                    <div
                      className={`max-w-[80%] rounded-2xl px-4 py-3 ${
                        msg.role === 'user'
                          ? 'rounded-tr-none bg-blue-500'
                          : 'rounded-tl-none bg-gray-100'
                      }`}
                    >
                      {msg.role === 'user' ? (
                        <p className="whitespace-pre-wrap text-white">
                          {msg.text}
                        </p>
                      ) : (
                        <div className="text-gray-800">
                          <MarkdownRenderer text={msg.text} />
                        </div>
                      )}
                      <div
                        className={`mt-2 text-xs ${msg.role === 'user' ? 'text-blue-200' : 'text-gray-500'}`}
                      >
                        {msg.role === 'user' ? 'You' : 'Assistant'} • Just now
                      </div>
                    </div>
                  </div>
                ))}

                {loading && (
                  <div className="flex gap-3">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-linear-to-r from-blue-500 to-purple-500">
                      <FaRobot className="h-4 w-4 text-white" />
                    </div>
                    <div className="rounded-2xl rounded-tl-none bg-gray-100 px-4 py-3">
                      <div className="flex items-center gap-1">
                        <div className="h-2 w-2 animate-bounce rounded-full bg-gray-400"></div>
                        <div
                          className="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                          style={{ animationDelay: '0.1s' }}
                        ></div>
                        <div
                          className="h-2 w-2 animate-bounce rounded-full bg-gray-400"
                          style={{ animationDelay: '0.2s' }}
                        ></div>
                      </div>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>
            )}
          </ScrollArea>
        </div>

        <div className="sticky bottom-0 border-t bg-white p-4">
          {error && (
            <div className="mb-3 rounded-lg bg-red-50 p-3">
              <p className="text-sm text-red-600">{error}</p>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <InputGroup>
              <InputGroupTextarea
                ref={textareaRef}
                value={question}
                onChange={e => setQuestion(e.target.value)}
                placeholder="Type your question here..."
                disabled={loading}
                className="min-h-11 resize-none rounded-xl border-gray-300 pr-12 focus:border-blue-500 focus:ring-blue-500"
                rows={1}
                onKeyDown={e => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleSubmit(e);
                  }
                }}
              />
              <InputGroupAddon align="block-end">
                <InputGroupButton
                  type="submit"
                  disabled={loading || !question.trim()}
                  variant="default"
                  size="icon-sm"
                  className={`ml-auto rounded-full ${
                    loading || !question.trim()
                      ? 'bg-gray-300 hover:bg-gray-300'
                      : 'bg-linear-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600'
                  }`}
                >
                  {loading ? (
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                  ) : (
                    <FaPaperPlane className="h-4 w-4" />
                  )}
                </InputGroupButton>
              </InputGroupAddon>
            </InputGroup>
            <div className="mt-2 flex items-center justify-between">
              <p className="text-xs text-gray-500">
                Press Enter to send • Shift + Enter for new line
              </p>
              <button
                type="button"
                onClick={() => setMessages([])}
                className="text-xs text-gray-500 hover:text-gray-700"
              >
                Clear chat
              </button>
            </div>
          </form>
        </div>
      </CardBody>
    </Card>
  );
}
